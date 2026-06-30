<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptNode;

/**
 * Подсказки текущего шага графа для тренажёра (телесуфлёр и сайдбар).
 */
final class TrainerScenarioGuidanceService
{
    /**
     * @param  array<string, mixed>  $playPresentation
     * @return list<array<string, mixed>>
     */
    public function build(?SalesScriptNode $current, array $playPresentation): array
    {
        if ($current === null) {
            return [];
        }

        $hints = [];
        $excerpt = filled($playPresentation['operator_line'] ?? null)
            ? (string) $playPresentation['operator_line']
            : (string) ($playPresentation['branch_instruction'] ?? '');

        if (trim($excerpt) !== '') {
            $hints[] = [
                'source' => 'graph_current_step',
                'node_id' => (int) $current->id,
                'client_key' => $playPresentation['step_key'] ?? $current->client_key,
                'kind' => $playPresentation['operator_kind'] ?? $current->kind->value,
                'excerpt' => $excerpt,
                'hint' => $playPresentation['coaching_hint'] ?? $current->hint,
                'is_current' => true,
            ];
        }

        foreach ($playPresentation['choices'] ?? [] as $choice) {
            if (! is_array($choice)) {
                continue;
            }

            if (($choice['sales_script_reaction_class_id'] ?? null) === null && ! (bool) ($choice['has_customer_phrase'] ?? false)) {
                continue;
            }

            $label = trim((string) ($choice['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $hints[] = [
                'source' => 'graph_client_option',
                'node_id' => (int) $current->id,
                'client_key' => $playPresentation['step_key'] ?? $current->client_key,
                'kind' => 'client_reaction',
                'excerpt' => $label,
                'hint' => filled($choice['subtitle'] ?? null) ? (string) $choice['subtitle'] : null,
                'is_current' => false,
            ];
        }

        return $hints;
    }
}
