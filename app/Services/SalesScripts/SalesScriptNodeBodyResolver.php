<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;

final class SalesScriptNodeBodyResolver
{
    /**
     * @return array{body: string, variant: string|null}
     */
    public function resolve(SalesScriptNode $node, SalesScriptPlaySession $session): array
    {
        if (! $node->ab_enabled || blank($node->body_variant_b)) {
            return [
                'body' => (string) $node->body,
                'variant' => 'a',
            ];
        }

        $weight = max(0, min(100, (int) ($node->ab_variant_b_weight ?? 50)));
        $bucket = abs(crc32($session->id.':'.$node->id)) % 100;
        $useVariantB = $bucket < $weight;

        return [
            'body' => $useVariantB ? (string) $node->body_variant_b : (string) $node->body,
            'variant' => $useVariantB ? 'b' : 'a',
        ];
    }

    public function nodeForDisplay(SalesScriptNode $node, SalesScriptPlaySession $session): SalesScriptNode
    {
        $resolved = $this->resolve($node, $session);
        $display = clone $node;
        $display->body = $resolved['body'];

        return $display;
    }
}
