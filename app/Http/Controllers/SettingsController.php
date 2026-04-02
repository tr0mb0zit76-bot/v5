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
                ],
                [
                    'key' => 'roles',
                    'title' => 'Роли',
                    'description' => 'Права, области видимости и системные ограничения по ролям.',
                    'href' => route('settings.roles.index'),
                ],
                [
                    'key' => 'table-presets',
                    'title' => 'Управление таблицей',
                    'description' => 'Ролевые пресеты колонок таблицы заказов как базовое представление для группы.',
                    'href' => route('settings.tables.index'),
                ],
                [
                    'key' => 'kpi-settings',
                    'title' => 'Настройки KPI',
                    'description' => 'Пороги KPI по типу сделки, мотивация менеджеров и база для расчета delta.',
                    'href' => route('settings.motivation.kpi'),
                ],
            ],
        ]);
    }
}
