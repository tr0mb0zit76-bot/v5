<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\Commercial\CommercialNudgeEvaluator;
use Illuminate\Support\Facades\Schema;

final class CardSmartLinksResolver
{
    public function __construct(
        private readonly CommercialNudgeEvaluator $nudgeEvaluator,
    ) {}

    /**
     * @return list<array{key: string, label: string, count: int, href: string|null}>
     */
    public function forLead(Lead $lead, ?User $user): array
    {
        if ($user === null || ! CrmFeatureCatalog::isEnabled('card_smart_links', $user)) {
            return [];
        }

        $links = [];

        if (Schema::hasTable('tasks')) {
            $openTasks = Task::query()
                ->where('lead_id', $lead->id)
                ->whereIn('status', TaskStatus::openStatuses())
                ->count();

            if ($openTasks > 0) {
                $links[] = $this->link(
                    key: 'tasks',
                    label: 'Задачи',
                    count: $openTasks,
                    href: route('leads.show', $lead).'#tasks',
                );
            }
        }

        if (Schema::hasTable('mail_threads') && RoleAccess::canAccessVisibilityArea($user, 'mail')) {
            $mailThreads = MailThread::query()->where('lead_id', $lead->id)->count();

            if ($mailThreads > 0) {
                $links[] = $this->link(
                    key: 'mail',
                    label: 'Почта',
                    count: $mailThreads,
                    href: route('mail.index'),
                );
            }
        }

        $attentionCount = count($this->nudgeEvaluator->matchesForLead($lead));

        if ($attentionCount > 0) {
            $links[] = $this->link(
                key: 'attention',
                label: 'Внимание',
                count: $attentionCount,
                href: route('leads.show', $lead),
            );
        }

        return $links;
    }

    /**
     * @return list<array{key: string, label: string, count: int, href: string|null}>
     */
    public function forOrder(Order $order, ?User $user): array
    {
        if ($user === null || ! CrmFeatureCatalog::isEnabled('card_smart_links', $user)) {
            return [];
        }

        $links = [];

        if (Schema::hasTable('tasks')) {
            $openTasks = Task::query()
                ->where('order_id', $order->id)
                ->whereIn('status', TaskStatus::openStatuses())
                ->count();

            if ($openTasks > 0) {
                $links[] = $this->link(
                    key: 'tasks',
                    label: 'Задачи',
                    count: $openTasks,
                    href: route('orders.edit', $order).'#tasks',
                );
            }
        }

        if (Schema::hasTable('mail_threads') && RoleAccess::canAccessVisibilityArea($user, 'mail')) {
            $mailThreads = MailThread::query()->where('order_id', $order->id)->count();

            if ($mailThreads > 0) {
                $links[] = $this->link(
                    key: 'mail',
                    label: 'Почта',
                    count: $mailThreads,
                    href: route('mail.index'),
                );
            }
        }

        return $links;
    }

    /**
     * @return array{key: string, label: string, count: int, href: string|null}
     */
    private function link(string $key, string $label, int $count, ?string $href): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => max(0, $count),
            'href' => $href,
        ];
    }
}
