<?php

namespace Database\Seeders;

use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SalesScriptsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $reactions = [
            ['key' => 'positive_signal', 'label' => 'Клиент позитивен / готов к расчёту', 'sort_order' => 10],
            ['key' => 'price_objection', 'label' => 'Возражение по цене', 'sort_order' => 20],
            ['key' => 'need_info', 'label' => 'Нужны уточнения по грузу / маршруту', 'sort_order' => 30],
            ['key' => 'stall', 'label' => 'Оттягивает / «подумаю»', 'sort_order' => 40],
            ['key' => 'competitor', 'label' => 'Уже работает с другой экспедицией', 'sort_order' => 50],
        ];

        $reactionIds = [];
        foreach ($reactions as $row) {
            $model = SalesScriptReactionClass::query()->firstOrCreate(
                ['key' => $row['key']],
                ['label' => $row['label'], 'sort_order' => $row['sort_order']],
            );
            $reactionIds[$row['key']] = $model->id;
        }

        $script = SalesScript::query()->firstOrCreate(
            ['title' => 'Первичный запрос ставки (экспедиция)'],
            [
                'description' => 'Пилотный сценарий: приветствие, квалификация, типовые ветки.',
                'channel' => 'phone',
                'tags' => ['экспедиция', 'ставка'],
            ],
        );

        $version = SalesScriptVersion::query()->firstOrCreate(
            [
                'sales_script_id' => $script->id,
                'version_number' => 1,
            ],
            [
                'published_at' => Carbon::now(),
                'is_active' => true,
                'entry_node_key' => 'intro',
            ],
        );

        $version->update([
            'published_at' => Carbon::now(),
            'is_active' => true,
            'entry_node_key' => 'intro',
        ]);

        $nodes = [
            ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Добрый день! Компания [название], меня зовут [имя]. Вы запрашивали расчёт по перевозке — удобно пару минут уточнить параметры?', 'hint' => 'Говорите спокойно, зафиксируйте контактное лицо.', 'sort_order' => 10],
            ['client_key' => 'qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Уточните маршрут, срок готовности груза и что именно везём (вес, объём, особые условия). После ответа клиента выберите тип реакции ниже.', 'hint' => 'Не озвучивайте ставку до минимальной квалификации.', 'sort_order' => 20],
            ['client_key' => 'price_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Понимаю, бюджет важен. Наша ставка учитывает маршрут, срок и ответственность за сопровождение. Давайте сверим, что входит в расчёт — так проще сравнить с альтернативами.', 'hint' => 'Переведите разговор в ценность: срок, страхование, мониторинг.', 'sort_order' => 30],
            ['client_key' => 'need_info', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Зафиксирую недостающие данные и вернусь с уточняющими вопросами или черновой ставкой в течение [X] часов.', 'hint' => 'Назовите реалистичный SLA.', 'sort_order' => 40],
            ['client_key' => 'positive', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отлично, данных достаточно для просчёта. Отправлю КП на почту / в мессенджер до [время]. Удобно?', 'hint' => 'Подтвердите канал и ФИО получателя.', 'sort_order' => 50],
            ['client_key' => 'wrapup', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Кратко подытожу: маршрут […], срок […], я готовлю ставку. Если появятся изменения по грузу — сразу напишите, скорректируем.', 'hint' => 'Попросите «ок» от клиента.', 'sort_order' => 60],
            ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Сценарий завершён. Зафиксируйте исход разговора и при необходимости главное возражение — это поможет обучать подсказки.', 'hint' => null, 'sort_order' => 70],
        ];

        $nodeIds = [];
        foreach ($nodes as $n) {
            $node = SalesScriptNode::query()->updateOrCreate(
                [
                    'sales_script_version_id' => $version->id,
                    'client_key' => $n['client_key'],
                ],
                [
                    'kind' => $n['kind'],
                    'body' => $n['body'],
                    'hint' => $n['hint'],
                    'sort_order' => $n['sort_order'],
                ],
            );
            $nodeIds[$n['client_key']] = $node->id;
        }

        SalesScriptTransition::query()->where('sales_script_version_id', $version->id)->delete();

        $transitions = [
            ['from' => 'intro', 'to' => 'qualify', 'reaction' => null],
            ['from' => 'qualify', 'to' => 'positive', 'reaction' => 'positive_signal'],
            ['from' => 'qualify', 'to' => 'price_objection', 'reaction' => 'price_objection'],
            ['from' => 'qualify', 'to' => 'need_info', 'reaction' => 'need_info'],
            ['from' => 'qualify', 'to' => 'wrapup', 'reaction' => 'stall'],
            ['from' => 'qualify', 'to' => 'price_objection', 'reaction' => 'competitor'],
            ['from' => 'price_objection', 'to' => 'wrapup', 'reaction' => null],
            ['from' => 'need_info', 'to' => 'wrapup', 'reaction' => null],
            ['from' => 'positive', 'to' => 'wrapup', 'reaction' => null],
            ['from' => 'wrapup', 'to' => 'end', 'reaction' => null],
        ];

        foreach ($transitions as $t) {
            SalesScriptTransition::query()->updateOrCreate(
                [
                    'sales_script_version_id' => $version->id,
                    'from_node_id' => $nodeIds[$t['from']],
                    'to_node_id' => $nodeIds[$t['to']],
                    'sales_script_reaction_class_id' => $t['reaction'] ? $reactionIds[$t['reaction']] : null,
                ],
                ['sort_order' => 0],
            );
        }
    }
}
