<?php

namespace App\Support;

final class OrderIntakeDraftNavigation
{
    public static function wizardPathForDraft(int $draftId): string
    {
        return route('orders.create', ['intake_draft' => $draftId], absolute: false);
    }

    /**
     * @param  array<string, mixed>  $toolResult
     */
    public static function pathAfterCreateDraftTool(string $toolName, array $toolResult): ?string
    {
        if ($toolName !== 'create_order_intake_draft_from_text') {
            return null;
        }

        if (isset($toolResult['error']) && is_string($toolResult['error']) && $toolResult['error'] !== '') {
            return null;
        }

        $draftId = (int) ($toolResult['draft_id'] ?? 0);

        return $draftId > 0 ? self::wizardPathForDraft($draftId) : null;
    }
}
