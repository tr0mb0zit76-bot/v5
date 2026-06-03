<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Support\RoleAccess;
use Illuminate\Console\Command;

class GrantSalesBookQuizAnalyticsCommand extends Command
{
    protected $signature = 'sales-book:grant-quiz-analytics
                            {--role=* : Системные имена ролей (можно несколько)}
                            {--all-book : Выдать всем ролям с доступом к Книге продаж}
                            {--dry-run : Только показать, без записи в БД}';

    protected $description = 'Добавить область sales_assistant_book_analytics выбранным ролям';

    public function handle(): int
    {
        $area = 'sales_assistant_book_analytics';
        $roleNames = array_values(array_filter(array_map(
            fn (mixed $name): string => trim((string) $name),
            (array) $this->option('role'),
        )));
        $dryRun = (bool) $this->option('dry-run');
        $allBook = (bool) $this->option('all-book');

        if ($roleNames === [] && ! $allBook) {
            $roleNames = ['supervisor', 'manager', 'head_manager', 'sales_manager'];
        }

        $roles = Role::query()->orderBy('name')->get();
        $updated = 0;

        foreach ($roles as $role) {
            if ($role->name === 'admin') {
                continue;
            }

            if (! $this->shouldGrant($role, $roleNames, $allBook)) {
                continue;
            }

            $areas = is_array($role->visibility_areas) ? $role->visibility_areas : [];

            if (in_array($area, $areas, true)) {
                $this->line("  = {$role->name}: уже есть «{$area}»");

                continue;
            }

            $nextAreas = array_values(array_unique([...$areas, $area]));

            $this->info(sprintf(
                '  + %s (%s): добавляем «%s»',
                $role->name,
                $role->display_name,
                $area,
            ));

            if (! $dryRun) {
                $role->update(['visibility_areas' => $nextAreas]);
            }

            $updated++;
        }

        if ($updated === 0) {
            $this->warn('Подходящих ролей не найдено. Укажите --role=… или --all-book.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($dryRun
            ? "Готово (dry-run): {$updated} рол(ей) будет обновлено."
            : "Готово: обновлено ролей — {$updated}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function shouldGrant(Role $role, array $roleNames, bool $allBook): bool
    {
        if ($roleNames !== [] && in_array($role->name, $roleNames, true)) {
            return true;
        }

        if (! $allBook) {
            return false;
        }

        $areas = RoleAccess::effectiveVisibilityAreasFromRolePayload($role->name, $role->visibility_areas ?? null);

        return RoleAccess::hasVisibilityArea($areas, 'sales_assistant_book');
    }
}
