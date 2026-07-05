<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Support\LeadIntakeMapper;
use App\Support\LeadStatus;
use Illuminate\Support\Facades\DB;

class LeadMessageIntakeService
{
    public function __construct(
        private readonly TransportTextIntakeService $transportTextIntakeService,
    ) {}

    /**
     * @return array{lead: Lead, parsed: array<string, mixed>, warnings: list<string>}
     */
    public function createFromText(string $message, User $user): array
    {
        $intake = $this->transportTextIntakeService->extract($user, $message);
        $mapped = LeadIntakeMapper::fromExtracted($intake['extracted'], $message, $intake['parser']);

        $metadata = [
            'traklo_message_intake' => array_merge($mapped['metadata_intake'], [
                'created_from_user_id' => $user->id,
                'submitted_at' => now()->toIso8601String(),
                'warnings' => $intake['warnings'],
            ]),
        ];

        $lead = Lead::query()->create([
            'number' => $this->nextLeadNumber(),
            'status' => LeadStatus::values()[0],
            'source' => 'traklo_message_intake',
            'responsible_id' => $user->id,
            'title' => $mapped['lead_attributes']['title'],
            'description' => $mapped['lead_attributes']['description'],
            'loading_location' => $mapped['lead_attributes']['loading_location'],
            'unloading_location' => $mapped['lead_attributes']['unloading_location'],
            'planned_shipping_date' => $mapped['lead_attributes']['planned_shipping_date'],
            'lead_qualification' => [
                'need' => 'Заявка из текста сообщения',
                'timeline' => null,
            ],
            'metadata' => $metadata,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            'lead' => $lead,
            'parsed' => $mapped['parsed'],
            'warnings' => $intake['warnings'],
        ];
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = DB::table('leads')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
