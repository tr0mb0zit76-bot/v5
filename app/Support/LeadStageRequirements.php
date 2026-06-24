<?php

namespace App\Support;

/**
 * Требования этапа воронки: blocking — без этого нельзя считать этап закрытым; recommended — желательно.
 *
 * @phpstan-type RequirementSet array{blocking: list<string>, recommended: list<string>}
 */
final class LeadStageRequirements
{
    /**
     * @return RequirementSet
     */
    public static function forLead(string $leadStatus, ?string $processSlug, ?string $stageName): array
    {
        if ($processSlug !== null && $stageName !== null) {
            $byProcess = self::byProcessAndStage();

            if (isset($byProcess[$processSlug][$stageName])) {
                return $byProcess[$processSlug][$stageName];
            }
        }

        return self::fallbackByStatus($leadStatus);
    }

    /**
     * @return array<string, array<string, RequirementSet>>
     */
    private static function byProcessAndStage(): array
    {
        return [
            'transport-intake' => [
                'Получение деталей по перевозке' => [
                    'blocking' => ['has_counterparty', 'has_route', 'has_cargo'],
                    'recommended' => ['has_lpr', 'has_open_task', 'has_next_contact'],
                ],
                'Расчёт цены' => [
                    'blocking' => ['has_route', 'has_cargo', 'has_client_price'],
                    'recommended' => ['has_offer'],
                ],
                'Согласование цены' => [
                    'blocking' => ['proposal_sent'],
                    'recommended' => ['has_next_contact', 'has_open_task'],
                ],
                'Подписание' => [
                    'blocking' => ['has_offer'],
                    'recommended' => ['has_open_task'],
                ],
                'Отказ' => [
                    'blocking' => ['close_outcome_set'],
                    'recommended' => [],
                ],
            ],
            'contract-signing' => [
                'Сбор документов' => [
                    'blocking' => ['has_counterparty'],
                    'recommended' => ['has_open_task', 'has_next_contact'],
                ],
                'Согласование' => [
                    'blocking' => ['has_counterparty'],
                    'recommended' => ['has_open_task', 'has_next_contact'],
                ],
                'Протокол разногласий' => [
                    'blocking' => ['has_counterparty'],
                    'recommended' => ['has_open_task'],
                ],
                'Подписан' => [
                    'blocking' => [],
                    'recommended' => ['has_open_task'],
                ],
            ],
        ];
    }

    /**
     * @return RequirementSet
     */
    private static function fallbackByStatus(string $leadStatus): array
    {
        if (LeadStatus::isClosed($leadStatus)) {
            return [
                'blocking' => ['close_outcome_set'],
                'recommended' => [],
            ];
        }

        if ($leadStatus === 'on_hold') {
            return [
                'blocking' => [],
                'recommended' => ['has_next_contact'],
            ];
        }

        return match ($leadStatus) {
            'new', 'qualification' => [
                'blocking' => ['has_counterparty', 'has_route', 'has_cargo'],
                'recommended' => ['has_lpr', 'has_open_task', 'has_next_contact'],
            ],
            'calculation' => [
                'blocking' => ['has_route', 'has_cargo', 'has_client_price'],
                'recommended' => ['has_offer'],
            ],
            'proposal_ready' => [
                'blocking' => ['has_offer'],
                'recommended' => ['proposal_sent'],
            ],
            'proposal_sent', 'negotiation' => [
                'blocking' => [],
                'recommended' => ['has_next_contact', 'has_open_task'],
            ],
            default => [
                'blocking' => [],
                'recommended' => ['has_next_contact'],
            ],
        };
    }
}
