<?php

namespace App\Services\SalesScripts;

use App\Models\Lead;
use App\Models\SalesScriptPlaySession;
use Illuminate\Support\Carbon;

class SalesScriptCaptureLeadMapper
{
    /**
     * @return array<string, string>
     */
    public function fieldsFromLead(Lead $lead): array
    {
        $lead->loadMissing('counterparty:id,name');

        $qualification = is_array($lead->lead_qualification) ? $lead->lead_qualification : [];
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $profile = is_array($metadata['acquaintance_profile'] ?? null)
            ? $metadata['acquaintance_profile']
            : [];
        $capture = is_array($metadata['sales_script_capture'] ?? null)
            ? $metadata['sales_script_capture']
            : [];

        return array_filter([
            'client_name' => $lead->counterparty?->name ?: $lead->title,
            'route_from' => $lead->loading_location ?: ($capture['route_from'] ?? null),
            'route_to' => $lead->unloading_location ?: ($capture['route_to'] ?? null),
            'loading_date' => $lead->planned_shipping_date?->toDateString() ?: ($capture['loading_date'] ?? null),
            'next_step_date' => $lead->next_contact_at?->toDateString() ?: ($capture['next_step_date'] ?? null),
            'decision_deadline' => $this->stringOrNull($qualification['timeline'] ?? null)
                ?: $lead->next_contact_at?->toDateString()
                ?: ($capture['decision_deadline'] ?? null),
            'cargo_type' => $this->stringOrNull($qualification['need'] ?? null) ?: ($capture['cargo_type'] ?? null),
            'budget_window' => $this->stringOrNull($qualification['budget'] ?? null) ?: ($capture['budget_window'] ?? null),
            'decision_criteria' => $this->stringOrNull($qualification['criteria'] ?? null)
                ?: $this->stringOrNull($profile['decision_criteria'] ?? null)
                ?: ($capture['decision_criteria'] ?? null),
            'email' => $this->stringOrNull($qualification['email'] ?? null) ?: ($capture['email'] ?? null),
            'routes' => $this->stringOrNull($profile['routes'] ?? null) ?: ($capture['routes'] ?? null),
            'volume_forecast' => $this->stringOrNull($profile['volume_forecast'] ?? null)
                ?: ($capture['volume_forecast'] ?? null),
            'payment_terms' => $this->stringOrNull($profile['payment_terms'] ?? null)
                ?: ($capture['payment_terms'] ?? null),
            'current_provider' => $this->stringOrNull($profile['current_provider'] ?? null)
                ?: ($capture['current_provider'] ?? null),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    /**
     * @return array<string, string>
     */
    public function fieldsFromSession(SalesScriptPlaySession $session): array
    {
        $session->loadMissing('fieldValues.captureField');

        $values = [];

        foreach ($session->fieldValues as $fieldValue) {
            $code = $fieldValue->captureField?->code;
            $value = trim((string) $fieldValue->value);

            if (is_string($code) && $code !== '' && $value !== '') {
                $values[$code] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $fields
     */
    public function applyToLead(Lead $lead, array $fields, ?int $userId = null): void
    {
        $normalized = [];
        foreach ($fields as $code => $value) {
            if (! is_string($code) || $code === '') {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[$code] = $trimmed;
        }

        if ($normalized === []) {
            return;
        }

        $qualification = is_array($lead->lead_qualification) ? $lead->lead_qualification : [];
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $profile = is_array($metadata['acquaintance_profile'] ?? null)
            ? $metadata['acquaintance_profile']
            : [];

        $updates = [];

        if (isset($normalized['route_from'])) {
            $updates['loading_location'] = $normalized['route_from'];
        }

        if (isset($normalized['route_to'])) {
            $updates['unloading_location'] = $normalized['route_to'];
        }

        if (isset($normalized['loading_date'])) {
            $date = $this->dateValue($normalized['loading_date']);
            if ($date !== null) {
                $updates['planned_shipping_date'] = $date;
            }
        }

        if (isset($normalized['next_step_date'])) {
            $nextContact = $this->dateTimeValue($normalized['next_step_date']);
            if ($nextContact !== null) {
                $updates['next_contact_at'] = $nextContact;
            }
        }

        if (isset($normalized['cargo_type'])) {
            $qualification['need'] = $normalized['cargo_type'];
        }

        if (isset($normalized['decision_deadline'])) {
            $qualification['timeline'] = $normalized['decision_deadline'];
        }

        if (isset($normalized['budget_window'])) {
            $qualification['budget'] = $normalized['budget_window'];
        }

        if (isset($normalized['decision_criteria'])) {
            $qualification['criteria'] = $normalized['decision_criteria'];
            $profile['decision_criteria'] = $normalized['decision_criteria'];
        }

        if (isset($normalized['email'])) {
            $qualification['email'] = $normalized['email'];
        }

        foreach (['routes', 'volume_forecast', 'payment_terms', 'current_provider'] as $profileKey) {
            if (isset($normalized[$profileKey])) {
                $profile[$profileKey] = $normalized[$profileKey];
            }
        }

        $metadata['sales_script_capture'] = array_merge(
            is_array($metadata['sales_script_capture'] ?? null) ? $metadata['sales_script_capture'] : [],
            $normalized,
        );

        if ($profile !== []) {
            $metadata['acquaintance_profile'] = $profile;
        }

        $updates['lead_qualification'] = $qualification;
        $updates['metadata'] = $metadata;

        if ($userId !== null) {
            $updates['updated_by'] = $userId;
        }

        $lead->forceFill($updates)->save();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function dateValue(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateTimeValue(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
