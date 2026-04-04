<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Settings/Index', [
            'sections' => [
                [
                    'key' => 'users',
                    'title' => 'Пользователи',
                    'description' => 'Управление учетными записями, статусами и назначением ролей.',
                    'href' => route('settings.users.index'),
                    'group' => 'Администрирование',
                    'icon' => 'users',
                    'accent' => 'slate',
                ],
                [
                    'key' => 'roles',
                    'title' => 'Роли',
                    'description' => 'Права, области видимости и системные ограничения по ролям.',
                    'href' => route('settings.roles.index'),
                    'group' => 'Администрирование',
                    'icon' => 'shield',
                    'accent' => 'slate',
                ],
                [
                    'key' => 'table-presets',
                    'title' => 'Управление таблицей',
                    'description' => 'Ролевые пресеты колонок таблицы заказов как базовое представление для группы.',
                    'href' => route('settings.tables.index'),
                    'group' => 'Конфигурация',
                    'icon' => 'table',
                    'accent' => 'amber',
                ],
                [
                    'key' => 'dictionaries',
                    'title' => 'Справочники',
                    'description' => 'Глобальные классификаторы и списки выбора для карточек, фильтров и отчётов.',
                    'href' => route('settings.dictionaries.index'),
                    'group' => 'Конфигурация',
                    'icon' => 'book-open',
                    'accent' => 'amber',
                ],
                [
                    'key' => 'motivation',
                    'title' => 'Мотивация',
                    'description' => 'Пороги KPI, множитель bonus в delta и индивидуальные условия сотрудников.',
                    'href' => route('settings.motivation.index'),
                    'group' => 'Конфигурация',
                    'icon' => 'trending-up',
                    'accent' => 'emerald',
                ],
            ],
        ]);
    }

    public function motivation(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('Settings/Motivation', [
            'sections' => [
                [
                    'key' => 'kpi-settings',
                    'title' => 'Настройки KPI',
                    'description' => 'Пороги KPI по типу сделки и множитель bonus в формуле delta.',
                    'href' => route('settings.motivation.kpi'),
                    'icon' => 'gauge',
                    'accent' => 'emerald',
                ],
                [
                    'key' => 'salary-settings',
                    'title' => 'Условия сотрудников',
                    'description' => 'Оклад, бонус и периоды действия индивидуальных условий по зарплате.',
                    'href' => route('settings.motivation.salary'),
                    'icon' => 'wallet',
                    'accent' => 'amber',
                ],
            ],
        ]);
    }
}
