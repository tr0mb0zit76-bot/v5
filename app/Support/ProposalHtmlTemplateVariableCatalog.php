<?php

namespace App\Support;

use App\Services\PrintFormVariableCatalog;

class ProposalHtmlTemplateVariableCatalog
{
    public function __construct(
        private readonly PrintFormVariableCatalog $printFormVariableCatalog,
    ) {}

    /**
     * @return list<array{path: string, label: string, group_name: string}>
     */
    public function catalogEntries(): array
    {
        return collect($this->printFormVariableCatalog->leadOptions())
            ->map(function (array $option): array {
                $path = (string) ($option['value'] ?? '');
                $group = explode('.', $path)[0] ?? 'lead';

                return [
                    'path' => $path,
                    'label' => (string) ($option['label'] ?? $path),
                    'group_name' => $group,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['path'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{path: string, label: string, group_name: string, sort_order: int}>
     */
    public function seedRows(): array
    {
        return collect($this->catalogEntries())
            ->values()
            ->map(fn (array $entry, int $index): array => [
                ...$entry,
                'sort_order' => $index + 1,
            ])
            ->all();
    }

    /**
     * @return list<array{path: string, label: string, group_name: string}>
     */
    public function optionsForUi(): array
    {
        return $this->catalogEntries();
    }
}
