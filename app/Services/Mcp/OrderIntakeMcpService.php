<?php

namespace App\Services\Mcp;

use App\Http\Requests\StoreOrderRequest;
use App\Models\OrderIntakeDraft;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\Agents\AgentWriteConfirmationService;
use App\Services\OrderIntakeGoldenLibraryService;
use App\Services\OrderIntakeLearnedPhrasesService;
use App\Services\Orders\OrderDocumentIntakeService;
use App\Services\OrderWizardService;
use App\Support\ActivityEventType;
use App\Support\OrderIntakeDraftNavigation;
use App\Support\OrderIntakeSchema;
use App\Support\OrderIntakeWizardPayloadBuilder;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderIntakeMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly OrderDocumentIntakeService $intakeExtractor,
        private readonly OrderIntakeLearnedPhrasesService $learnedPhrases,
        private readonly OrderIntakeGoldenLibraryService $goldenLibrary,
        private readonly OrderWizardService $orderWizard,
        private readonly AgentWriteConfirmationService $writeConfirmation,
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createDraftFromText(User $user, string $instruction): array
    {
        $this->access->requireOrdersArea($user);

        return $this->intakeExtractor->extractFromText($user, $instruction, 'mcp:instruction', 'mcp');
    }

    /**
     * @return array<string, mixed>
     */
    public function extractDraftFromDocument(
        User $user,
        string $fileName,
        string $contentBase64,
        string $mimeType = 'application/octet-stream',
    ): array {
        $this->access->requireOrdersArea($user);

        $binary = base64_decode($contentBase64, true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'content_base64' => 'Некорректное содержимое файла (base64).',
            ]);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'mcp-intake-');

        if ($tempPath === false) {
            throw ValidationException::withMessages([
                'content_base64' => 'Не удалось создать временный файл.',
            ]);
        }

        file_put_contents($tempPath, $binary);

        try {
            $uploaded = new UploadedFile(
                $tempPath,
                $fileName !== '' ? $fileName : 'intake.pdf',
                $mimeType !== '' ? $mimeType : null,
                null,
                true,
            );

            return $this->intakeExtractor->extractFromUpload($user, $uploaded);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function applyWizardDraft(
        User $user,
        int $draftId,
        bool $dryRun = false,
        ?string $confirmToken = null,
    ): array {
        $this->access->requireOrdersArea($user);

        $draft = $this->resolveDraftForUser($user, $draftId);
        $patch = OrderIntakeSchema::sanitizeWizardPatch(
            is_array($draft->wizard_patch) ? $draft->wizard_patch : [],
        );

        $payload = OrderIntakeWizardPayloadBuilder::fromWizardPatch($patch, $user);
        $validated = $this->validateWizardPayload($user, $payload);

        $args = ['draft_id' => $draftId];
        $preview = [
            'draft_id' => $draftId,
            'order_preview' => [
                'client_id' => $validated['client_id'] ?? null,
                'own_company_id' => $validated['own_company_id'] ?? null,
                'loading_date' => $validated['loading_date'] ?? null,
                'unloading_date' => $validated['unloading_date'] ?? null,
                'route_points' => $validated['route_points'] ?? [],
                'financial_term' => $validated['financial_term'] ?? [],
            ],
            'warnings' => $draft->warnings ?? [],
            'confidence' => $draft->confidence,
        ];

        if ($dryRun) {
            $token = $this->writeConfirmation->issue($user, 'apply_order_wizard_draft', $args, $preview);

            return [
                'dry_run' => true,
                'preview' => $preview,
                'confirm_token' => $token,
                'confirm_ttl_seconds' => 900,
                'note' => 'Повторите вызов apply_order_wizard_draft с тем же draft_id и confirm_token без dry_run.',
            ];
        }

        $this->writeConfirmation->consume($user, 'apply_order_wizard_draft', (string) $confirmToken, $args);

        $order = $this->orderWizard->create($validated, $user);

        $this->goldenLibrary->commit($user, $draftId, $order->id, $validated);

        $this->activityLedger->record(
            $order,
            ActivityEventType::OrderIntakeApplied,
            'Заказ создан из заявки',
            sprintf('Черновик #%d применён через MCP.', $draftId),
            [
                'draft_id' => $draftId,
                'source_original_name' => $draft->source_original_name,
                'confidence' => $draft->confidence,
            ],
            null,
            $user,
            $draft,
        );

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'edit_path' => route('orders.edit', $order, absolute: false),
            'draft_id' => $draftId,
        ];
    }

    /**
     * @return array{ok: bool, id: int, message: string}
     */
    public function rememberPhrase(User $user, string $sourcePhrase, string $canonicalValue, string $field): array
    {
        $this->access->requireOrdersArea($user);

        $result = $this->learnedPhrases->remember($user, $sourcePhrase, $canonicalValue, $field);

        if ($result['ok'] ?? false) {
            $this->goldenLibrary->recordDialogLearning($user, $sourcePhrase, $canonicalValue, $field);
        }

        return $result;
    }

    public function activateDraftForLearning(User $user, int $draftId): void
    {
        $this->access->requireOrdersArea($user);

        $this->goldenLibrary->activateDraft($user, $draftId);
    }

    public function discardDraftLearning(User $user, int $draftId): bool
    {
        $this->access->requireOrdersArea($user);

        return $this->goldenLibrary->discard($user, $draftId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDraft(User $user, int $draftId): array
    {
        $this->access->requireOrdersArea($user);

        if (! Schema::hasTable('order_intake_drafts')) {
            throw new ModelNotFoundException('Таблица черновиков заявок недоступна.');
        }

        $draft = $this->resolveDraftForUser($user, $draftId);

        return $this->serializeDraft($draft);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentDrafts(User $user, int $limit = 10): array
    {
        $this->access->requireOrdersArea($user);

        if (! Schema::hasTable('order_intake_drafts')) {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $query = OrderIntakeDraft::query()
            ->orderByDesc('id')
            ->limit($limit);

        if (! $this->canViewOtherUsersDrafts($user)) {
            $query->where('user_id', $user->id);
        }

        return $query
            ->get()
            ->map(fn (OrderIntakeDraft $draft): array => $this->serializeDraftSummary($draft))
            ->all();
    }

    private function canViewOtherUsersDrafts(User $user): bool
    {
        return RoleAccess::canAccessSettingsSystem($user);
    }

    private function resolveDraftForUser(User $user, int $draftId): OrderIntakeDraft
    {
        if (! Schema::hasTable('order_intake_drafts')) {
            throw new ModelNotFoundException('Таблица черновиков заявок недоступна.');
        }

        $draft = OrderIntakeDraft::query()->findOrFail($draftId);

        if ((int) $draft->user_id !== (int) $user->id && ! $this->canViewOtherUsersDrafts($user)) {
            throw new AuthenticationException('Нет доступа к этому черновику заявки.');
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateWizardPayload(User $user, array $payload): array
    {
        $request = Request::create(route('orders.store'), 'POST', $payload);
        $request->setUserResolver(fn (): User => $user);

        /** @var StoreOrderRequest $formRequest */
        $formRequest = StoreOrderRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));

        if (! $formRequest->authorize()) {
            throw new AuthenticationException('Нет прав на создание заказа.');
        }

        $validator = app('validator')->make(
            $formRequest->all(),
            $formRequest->rules(),
        );

        foreach ($formRequest->after() as $after) {
            $after($validator);
        }

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $formRequest->merge($validator->validated());

        return $formRequest->validatedForWizard();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDraft(OrderIntakeDraft $draft): array
    {
        $wizardPatch = is_array($draft->wizard_patch) ? $draft->wizard_patch : [];

        return [
            'draft_id' => $draft->id,
            'wizard_path' => OrderIntakeDraftNavigation::wizardPathForDraft($draft->id),
            'user_id' => $draft->user_id,
            'order_id' => $draft->order_id,
            'source_original_name' => $draft->source_original_name,
            'confidence' => $draft->confidence,
            'warnings' => $draft->warnings ?? [],
            'wizard_patch' => OrderIntakeSchema::sanitizeWizardPatch($wizardPatch),
            'matched_contractors' => $draft->matched_contractors ?? [],
            'extracted' => $draft->extracted_payload ?? [],
            'created_at' => optional($draft->created_at)?->toIso8601String(),
            'note' => 'Откройте мастер заказа по wizard_path (в CRM: /orders/create?intake_draft={draft_id}). Command bar откроет автоматически.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDraftSummary(OrderIntakeDraft $draft): array
    {
        return [
            'draft_id' => $draft->id,
            'user_id' => $draft->user_id,
            'source_original_name' => $draft->source_original_name,
            'confidence' => $draft->confidence,
            'summary' => $this->draftSummaryLine($draft),
            'wizard_patch' => OrderIntakeSchema::sanitizeWizardPatch(
                is_array($draft->wizard_patch) ? $draft->wizard_patch : [],
            ),
            'matched_contractors' => $draft->matched_contractors ?? [],
            'created_at' => optional($draft->created_at)?->toIso8601String(),
        ];
    }

    private function draftSummaryLine(OrderIntakeDraft $draft): string
    {
        $patch = is_array($draft->wizard_patch) ? $draft->wizard_patch : [];
        $parts = [];

        $points = is_array($patch['route_points'] ?? null) ? $patch['route_points'] : [];
        $loading = collect($points)->firstWhere('type', 'loading');
        $unloading = collect($points)->firstWhere('type', 'unloading');

        if (is_array($loading) && filled($loading['address'] ?? null)) {
            $parts[] = 'погрузка: '.$loading['address'];
        }

        if (is_array($unloading) && filled($unloading['address'] ?? null)) {
            $parts[] = 'выгрузка: '.$unloading['address'];
        }

        $cargo = is_array($patch['cargo_items'] ?? null) ? ($patch['cargo_items'][0] ?? null) : null;

        if (is_array($cargo) && filled($cargo['name'] ?? null)) {
            $parts[] = 'груз: '.$cargo['name'];
        }

        return $parts !== [] ? implode(' · ', $parts) : (string) $draft->source_original_name;
    }
}
