<?php

namespace App\Services\Mcp;

use App\Services\McpIntegrationService;
use App\Support\McpToolDomainRegistry;
use RuntimeException;

final class McpCrossDomainGuard
{
    public function __construct(
        private readonly McpIntegrationService $integrations,
    ) {}

    public function enforce(string $toolName): void
    {
        if (! $this->integrations->hasConfiguredLinks()) {
            return;
        }

        $config = McpToolDomainRegistry::toolConfig($toolName);

        if ($config === null || $config['cross'] === []) {
            return;
        }

        $source = $config['domain'];

        foreach ($config['cross'] as $target) {
            if ($source === $target) {
                continue;
            }

            if (! $this->integrations->canExchangeData($source, $target)) {
                throw new RuntimeException(sprintf(
                    'Обмен данными между MCP-доменами «%s» и «%s» не разрешён. Настройте связь в Конфигурация → Связи MCP.',
                    $source,
                    $target,
                ));
            }
        }
    }
}
