<?php

namespace App\Support\MailSync;

use App\Models\MailThread;
use App\Models\User;
use App\Services\Commercial\MailMailboxAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class MailMailboxOwnerCatalog
{
    public function __construct(
        private readonly MailMailboxAuthorization $mailboxAuth,
    ) {}

    public static function shortLabel(User $user): string
    {
        $parts = preg_split('/\s+/u', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts !== false && $parts !== []) {
            return $parts[0];
        }

        $email = trim((string) $user->email);

        return $email !== '' ? explode('@', $email)[0] : 'Ящик #'.$user->id;
    }

    /**
     * @return array{
     *     can_view_all_mailboxes: bool,
     *     selected_mailbox_user_id: int|null,
     *     owners: list<array{user_id: int, label: string, full_name: string, email: string|null, thread_count: int}>,
     *     unassigned_thread_count: int,
     *     total_thread_count: int
     * }
     */
    public function viewContext(User $viewer, Request $request): array
    {
        $canViewAll = $this->mailboxAuth->canViewAllMailboxes($viewer);

        if (! $canViewAll || ! Schema::hasTable('mail_threads') || ! Schema::hasColumn('mail_threads', 'mailbox_user_id')) {
            return [
                'can_view_all_mailboxes' => false,
                'selected_mailbox_user_id' => null,
                'owners' => [],
                'unassigned_thread_count' => 0,
                'total_thread_count' => 0,
            ];
        }

        $selectedMailboxUserId = $this->resolveSelectedMailboxUserId($viewer, $request);

        $ownerRows = MailThread::query()
            ->selectRaw('mailbox_user_id, COUNT(*) as thread_count, MAX(last_message_at) as latest_at')
            ->whereNotNull('mailbox_user_id')
            ->groupBy('mailbox_user_id')
            ->orderByDesc('latest_at')
            ->get();

        $users = User::query()
            ->whereIn('id', $ownerRows->pluck('mailbox_user_id')->filter()->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $owners = $ownerRows
            ->map(function ($row) use ($users): ?array {
                $userId = (int) $row->mailbox_user_id;
                $user = $users->get($userId);

                if ($user === null) {
                    return null;
                }

                return [
                    'user_id' => $userId,
                    'label' => self::shortLabel($user),
                    'full_name' => (string) $user->name,
                    'email' => $user->email,
                    'thread_count' => (int) $row->thread_count,
                ];
            })
            ->filter()
            ->sortBy(fn (array $owner): string => mb_strtolower($owner['label']))
            ->values()
            ->all();

        $unassignedThreadCount = (int) MailThread::query()->whereNull('mailbox_user_id')->count();
        $totalThreadCount = (int) MailThread::query()->count();

        return [
            'can_view_all_mailboxes' => true,
            'selected_mailbox_user_id' => $selectedMailboxUserId,
            'owners' => $owners,
            'unassigned_thread_count' => $unassignedThreadCount,
            'total_thread_count' => $totalThreadCount,
        ];
    }

    public function resolveSelectedMailboxUserId(User $viewer, Request $request): ?int
    {
        if (! $this->mailboxAuth->canViewAllMailboxes($viewer) || ! $request->has('mailbox')) {
            return null;
        }

        return (int) $request->query('mailbox');
    }

    public function applyMailboxFilterToQuery(Builder $query, User $viewer, ?int $mailboxUserId): void
    {
        if (! $this->mailboxAuth->canViewAllMailboxes($viewer) || $mailboxUserId === null) {
            return;
        }

        if ($mailboxUserId === 0) {
            $query->whereNull('mailbox_user_id');

            return;
        }

        $query->where('mailbox_user_id', $mailboxUserId);
    }
}
