<?php

namespace App\Services\SalesScripts;

use App\Models\Order;
use App\Models\SalesScriptPlaySession;

final class SalesScriptPlayContextResolver
{
    /**
     * @return array<string, string>
     */
    public function resolveForSession(?int $orderId, ?int $contractorId = null): array
    {
        $tags = [];

        if ($contractorId !== null) {
            $tags['contractor_id'] = (string) $contractorId;
        }

        if ($orderId === null) {
            return $tags;
        }

        $order = Order::query()
            ->with(['routePoints' => fn ($query) => $query->orderBy('sequence')])
            ->find($orderId);

        if ($order === null) {
            return $tags;
        }

        $tags['order_id'] = (string) $order->id;

        if ($order->lead_id !== null) {
            $tags['lead_id'] = (string) $order->lead_id;
        }

        if ((bool) ($order->is_international_transport ?? false)) {
            $tags['direction'] = 'international';
        } else {
            $tags['direction'] = 'domestic';
        }

        $loadingPoint = $order->routePoints->firstWhere('type', 'loading');
        $unloadingPoint = $order->routePoints->last(fn ($point) => $point->type === 'unloading');

        $loadingCity = data_get($loadingPoint?->normalized_data, 'city');
        $unloadingCity = data_get($unloadingPoint?->normalized_data, 'city');

        if (is_string($loadingCity) && trim($loadingCity) !== '') {
            $tags['loading_city'] = trim($loadingCity);
        }

        if (is_string($unloadingCity) && trim($unloadingCity) !== '') {
            $tags['unloading_city'] = trim($unloadingCity);
        }

        return $tags;
    }

    /**
     * @return array<string, string>
     */
    public function tagsForSession(SalesScriptPlaySession $session): array
    {
        $stored = is_array($session->context_tags) ? $session->context_tags : [];

        if ($stored !== []) {
            return $this->normalizeTags($stored);
        }

        return $this->resolveForSession($session->order_id, $session->contractor_id);
    }

    /**
     * @param  array<string, mixed>  $tags
     * @return array<string, string>
     */
    private function normalizeTags(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }

            $normalized[$key] = $stringValue;
        }

        return $normalized;
    }
}
