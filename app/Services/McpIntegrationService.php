<?php

namespace App\Services;

use App\Models\McpDataLink;
use App\Support\McpIntegrationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class McpIntegrationService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('mcp_data_links');
    }

    public function hasConfiguredLinks(): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }

        return McpDataLink::query()->where('is_active', true)->exists();
    }

    /**
     * @return list<array{id: int, source_key: string, target_key: string, bidirectional: bool, is_active: bool, label: string|null, notes: string|null}>
     */
    public function listLinks(): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return McpDataLink::query()
            ->orderBy('source_key')
            ->orderBy('target_key')
            ->get()
            ->map(fn (McpDataLink $link): array => [
                'id' => $link->id,
                'source_key' => $link->source_key,
                'target_key' => $link->target_key,
                'bidirectional' => (bool) $link->bidirectional,
                'is_active' => (bool) $link->is_active,
                'label' => $link->label,
                'notes' => $link->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{source_key: string, target_key: string, bidirectional?: bool, is_active?: bool, label?: string|null, notes?: string|null}>  $links
     */
    public function syncLinks(array $links): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $normalized = collect($links)
            ->map(function (array $row): ?array {
                $source = trim((string) ($row['source_key'] ?? ''));
                $target = trim((string) ($row['target_key'] ?? ''));

                if ($source === '' || $target === '' || $source === $target) {
                    return null;
                }

                if (! McpIntegrationCatalog::isKnownNode($source) || ! McpIntegrationCatalog::isKnownNode($target)) {
                    return null;
                }

                [$a, $b] = $source < $target ? [$source, $target] : [$target, $source];

                return [
                    'source_key' => $a,
                    'target_key' => $b,
                    'bidirectional' => (bool) ($row['bidirectional'] ?? true),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'label' => filled($row['label'] ?? null) ? trim((string) $row['label']) : null,
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                ];
            })
            ->filter()
            ->unique(fn (array $row): string => $row['source_key'].'|'.$row['target_key'])
            ->values();

        DB::transaction(function () use ($normalized): void {
            McpDataLink::query()->delete();

            foreach ($normalized as $row) {
                McpDataLink::query()->create($row);
            }
        });
    }

    public function canExchangeData(string $sourceKey, string $targetKey): bool
    {
        if ($sourceKey === $targetKey) {
            return true;
        }

        if (! $this->tablesReady()) {
            return false;
        }

        [$a, $b] = $sourceKey < $targetKey ? [$sourceKey, $targetKey] : [$targetKey, $sourceKey];

        $link = McpDataLink::query()
            ->where('source_key', $a)
            ->where('target_key', $b)
            ->where('is_active', true)
            ->first();

        if ($link === null) {
            return false;
        }

        if ($link->bidirectional) {
            return true;
        }

        return $link->source_key === $sourceKey && $link->target_key === $targetKey;
    }
}
