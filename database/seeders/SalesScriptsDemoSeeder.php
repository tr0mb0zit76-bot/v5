<?php

namespace Database\Seeders;

use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
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
        $reactionIds = $this->seedReactionClasses();
        $this->seedCaptureFields();

        $this->seedScript(
            title: 'Первичный запрос ставки (экспедиция)',
            description: 'Пилотный сценарий: приветствие, квалификация и типовые ветки.',
            channel: 'phone',
            tags: ['экспедиция', 'ставка'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: получить разрешение на короткую квалификацию, не продавать ставку сразу. Сказать: «Добрый день. Вы оставили/отправили запрос на перевозку. Чтобы дать ставку без угадывания, уточню 5 параметров: маршрут, груз, даты, требования к машине и срок принятия решения. Займёт 2 минуты, удобно?» Если клиент торопится — предложите зафиксировать минимум и вернуться с расчётом.', 'hint' => 'Не спорьте за цену в первом шаге. Сначала получите право задать вопросы.', 'sort_order' => 10, 'tags' => ['старт', 'рамка']],
                ['client_key' => 'qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Спросить и заполнить поля: имя собеседника — {client_name}; откуда груз — {route_from}; куда — {route_to}; что везём — {cargo_type}; дата готовности — {loading_date}; когда нужно решение — {decision_deadline}. Сказать после вопросов: «Спасибо, теперь я понимаю контур. Следующий шаг — сверим ограничения и выберем: считать КП, запросить данные или закрыть вопрос позже».', 'hint' => 'Минимум для ставки: маршрут, груз, дата, требования к авто, дедлайн решения. Без этого не называйте точную цену.', 'sort_order' => 20, 'tags' => ['квалификация', 'данные для ставки'], 'capture_field_codes' => ['client_name', 'route_from', 'route_to', 'cargo_type', 'loading_date', 'decision_deadline']],
                ['client_key' => 'price_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: не оправдываться, а вернуть сравнение на одинаковые условия. Сказать: «Понимаю, ставка важна. Давайте сравнивать не голую цифру, а одинаковый набор: срок подачи, тип транспорта, страхование, контроль статусов, риски простоя и документы. Я могу дать дешевле только если осознанно убрать часть требований. Что для вас точно нельзя терять?» Зафиксируйте критерии выбора — {decision_criteria}.', 'hint' => 'Формула ответа: признать цену → разложить состав услуги → спросить, чем клиент готов рисковать.', 'sort_order' => 30, 'tags' => ['возражение', 'цена'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'need_info', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: превратить нехватку данных в конкретную задачу. Сказать: «Чтобы не прислать расчёт, который потом придётся переделывать, мне нужны недостающие параметры. Я отправлю короткий список: маршрут, груз, дата, требования к машине, контакт на погрузке. Как только получу — вернусь с вилкой ставки и пояснением». Уточните канал для КП — {email}.', 'hint' => 'Всегда называйте следующий срок: сегодня до конца дня, завтра до 11:00 или другой реалистичный SLA.', 'sort_order' => 40, 'tags' => ['нужны данные'], 'capture_field_codes' => ['email']],
                ['client_key' => 'positive', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: закрыть договорённость на следующий шаг. Сказать: «Отлично, {client_name}. По маршруту {route_from} — {route_to}, груз: {cargo_type}, готовность: {loading_date}. Я готовлю КП с условиями, сроком подачи и ответственным менеджером. Куда отправить и кто будет принимать решение?» Заполните канал/почту — {email}.', 'hint' => 'Не заканчивайте на «я отправлю». Заканчивайте на конкретном времени и ответственном лице.', 'sort_order' => 50, 'tags' => ['КП', 'следующий шаг'], 'capture_field_codes' => ['email']],
                ['client_key' => 'wrapup', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Подытожить вслух: «Фиксирую: маршрут {route_from} — {route_to}, груз {cargo_type}, готовность {loading_date}, дедлайн решения {decision_deadline}. Мой следующий шаг — подготовить КП/запросить недостающие данные. Ваш следующий шаг — подтвердить параметры или прислать документы». Попросите клиента подтвердить, что всё записано верно.', 'hint' => 'Сводка в конце снижает риск ошибок в заявке и помогает потом завести лид/заказ.', 'sort_order' => 60, 'tags' => ['итог', 'контроль']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После разговора: выберите исход сессии, укажите главное возражение, добавьте заметку в лид или заказ. Если обещали КП — создайте задачу с точным сроком и приложите параметры перевозки из заполненных полей.', 'hint' => 'Сценарий считается рабочим только если после него есть следующий шаг в CRM.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'qualify', 'reaction' => null, 'customer_label' => 'Да, удобно, уточняйте параметры'],
                ['from' => 'qualify', 'to' => 'positive', 'reaction' => 'positive_signal', 'customer_label' => 'Да, данных достаточно, давайте считать'],
                ['from' => 'qualify', 'to' => 'price_objection', 'reaction' => 'price_objection', 'customer_label' => 'Сначала скажите, сколько будет стоить'],
                ['from' => 'qualify', 'to' => 'need_info', 'reaction' => 'need_info', 'customer_label' => 'Мне нужно уточнить адреса/груз у коллег'],
                ['from' => 'qualify', 'to' => 'wrapup', 'reaction' => 'stall', 'customer_label' => 'Пока не срочно, вернёмся позже'],
                ['from' => 'qualify', 'to' => 'price_objection', 'reaction' => 'competitor', 'customer_label' => 'У другого перевозчика уже есть ставка'],
                ['from' => 'price_objection', 'to' => 'wrapup', 'reaction' => null, 'customer_label' => 'Понял, сравним на одинаковых условиях'],
                ['from' => 'need_info', 'to' => 'wrapup', 'reaction' => null, 'customer_label' => 'Пришлите список, я соберу данные'],
                ['from' => 'positive', 'to' => 'wrapup', 'reaction' => null, 'customer_label' => 'Отправляйте КП, ждём расчёт'],
                ['from' => 'wrapup', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, всё записано верно'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Холодный звонок',
            description: 'Выход на ЛПР по логистике: открытие, калибровка, СПИН-вопросы и фиксация следующего шага.',
            channel: 'phone',
            tags: ['холодный звонок', 'ЛПР', 'СПИН', 'НЛП'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: за 20 секунд получить право говорить с тем, кто отвечает за перевозки. Сказать: «Добрый день. Подскажите, кто у вас отвечает за логистику, выбор перевозчиков и ставки по маршрутам? Я не с рекламой на пять минут: хочу понять, есть ли у нас точка полезности по срокам, резерву машин или документам».', 'hint' => 'Не начинайте с презентации компании. Первый успех — найти ЛПР или корректный маршрут к нему.', 'sort_order' => 10, 'tags' => ['старт', 'выход на ЛПР']],
                ['client_key' => 'gatekeeper_branch', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Выберите реакцию: если соединяют с ЛПР — переходите к открытию; если просят «что хотели» — коротко сформулируйте пользу; если отправляют на почту — возьмите имя, должность, адрес и повод для письма.', 'hint' => 'Секретарь не враг. Его задача — фильтровать шум; ваша задача — дать понятный деловой повод.', 'sort_order' => 20, 'tags' => ['секретарь', 'ветвление']],
                ['client_key' => 'clarify_contact', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Спросить: «Как правильно зовут ответственного за перевозки? Какая должность? На какую почту отправить короткое письмо? Когда лучше набрать, чтобы не отвлекать?» Заполните имя — {client_name}, канал/почту — {email}, дату следующего касания — {next_step_date}.', 'hint' => 'Не просите «дайте телефон». Просите «как корректно обратиться и когда удобно».', 'sort_order' => 30, 'tags' => ['контакт', 'следующее касание'], 'capture_field_codes' => ['client_name', 'email', 'next_step_date']],
                ['client_key' => 'lpr_open', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: договориться на короткую диагностику. Сказать ЛПР: «Чтобы не рассказывать лишнее, задам 3 вопроса по перевозкам. Если поймём, что пользы нет — честно закончим. Если есть точка улучшения — предложу конкретный следующий шаг».', 'hint' => 'Фрейм «короткая диагностика» снижает сопротивление и не звучит как продажа.', 'sort_order' => 40, 'tags' => ['ЛПР', 'рамка']],
                ['client_key' => 'spin_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'СПИН-вопросы: 1) Ситуация: какие направления и объёмы регулярные? Заполните {routes} и {volume_forecast}. 2) Проблема: где чаще всего срывы — машина, срок, документы, связь? 3) Последствие: во что это обходится — штрафы, простой, ручной контроль? 4) Потребность: что должно измениться, чтобы вы сказали «стало лучше»? Заполните критерии — {decision_criteria}.', 'hint' => 'Не перебивайте после первого ответа. Главная ценность холодного звонка — найти боль словами клиента.', 'sort_order' => 50, 'tags' => ['СПИН', 'диагностика'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
                ['client_key' => 'value_pitch', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Связать предложение с болью клиента: «Если главный риск — срыв сроков/статусов/документов, мы можем начать не с большого договора, а с пилотного маршрута: фиксируем KPI, ответственного, регламент статусов и сравниваем результат с текущей схемой». Затем предложите 20-минутный разбор.', 'hint' => 'Питч должен отвечать на названную проблему, а не перечислять все услуги.', 'sort_order' => 60, 'tags' => ['ценность', 'пилот']],
                ['client_key' => 'soft_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Ответ на «не актуально/уже работаем/дорого»: «Понимаю. Я не предлагаю менять текущую схему. Предлагаю проверить один маршрут или один критерий, где обычно бывают риски. Если цифры и процесс не лучше — вы ничего не теряете». Затем спросите: «Какой маршрут показательный для проверки?»', 'hint' => 'Снимайте риск изменения: один маршрут, один критерий, ограниченный срок.', 'sort_order' => 70, 'tags' => ['возражение', 'снижение риска']],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Зафиксировать следующий шаг: дата/время — {next_step_date}, участники, какие данные нужны для расчёта, куда отправить повестку — {email}. Сказать: «Я пришлю короткое резюме: что услышал, какой пилот предлагаю и какие данные нужны. Если что-то не так — поправите до созвона».', 'hint' => 'Без даты следующего шага холодный звонок считается незавершённым.', 'sort_order' => 80, 'tags' => ['следующий шаг'], 'capture_field_codes' => ['next_step_date', 'email']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После звонка: отметьте, вышли ли на ЛПР, какую проблему назвали, какой следующий шаг согласован, какое возражение было главным. Если контакта нет — создайте задачу повторного касания с конкретной датой.', 'hint' => 'Холодный звонок работает только через дисциплину повторных касаний.', 'sort_order' => 90, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'gatekeeper_branch', 'reaction' => null, 'customer_label' => 'Слушаю, что у вас за вопрос?'],
                ['from' => 'gatekeeper_branch', 'to' => 'lpr_open', 'reaction' => 'positive_signal', 'customer_label' => 'Да, это я / соединяю с ЛПР'],
                ['from' => 'gatekeeper_branch', 'to' => 'clarify_contact', 'reaction' => 'need_info', 'customer_label' => 'Скажите, что вы хотели?'],
                ['from' => 'gatekeeper_branch', 'to' => 'clarify_contact', 'reaction' => 'stall', 'customer_label' => 'Не сейчас, напишите на почту'],
                ['from' => 'clarify_contact', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Хорошо, запишите контакт и напишите'],
                ['from' => 'lpr_open', 'to' => 'spin_probe', 'reaction' => null, 'customer_label' => 'Хорошо, задавайте вопросы'],
                ['from' => 'spin_probe', 'to' => 'value_pitch', 'reaction' => 'positive_signal', 'customer_label' => 'Да, такая проблема есть, интересно'],
                ['from' => 'spin_probe', 'to' => 'soft_objection', 'reaction' => 'price_objection', 'customer_label' => 'Главное, чтобы было дешевле'],
                ['from' => 'spin_probe', 'to' => 'soft_objection', 'reaction' => 'competitor', 'customer_label' => 'У нас уже есть постоянный перевозчик'],
                ['from' => 'spin_probe', 'to' => 'next_step', 'reaction' => 'need_info', 'customer_label' => 'Нужно понять объёмы и маршруты у коллег'],
                ['from' => 'spin_probe', 'to' => 'next_step', 'reaction' => 'stall', 'customer_label' => 'Сейчас не время, вернитесь позже'],
                ['from' => 'value_pitch', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Давайте назначим короткий разбор'],
                ['from' => 'soft_objection', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Хорошо, пришлите предложение по одному маршруту'],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, дату и данные зафиксировали'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Знакомство',
            description: 'Диалог с ЛПР: критерии выбора перевозчиков, процедуры входа, текущий процесс и зоны улучшения.',
            channel: 'meeting',
            tags: ['знакомство', 'ЛПР', 'СПИН', 'квалификация'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель встречи: понять процесс клиента и выйти на дорожную карту входа. Сказать: «Предлагаю структуру на 25 минут: 10 минут — ваш процесс и критерии, 10 минут — где можем быть полезны, 5 минут — следующий шаг. Если поймём, что не подходим, так и зафиксируем».', 'hint' => 'Задайте рамку встречи до вопросов, иначе разговор уйдёт в хаотичную презентацию.', 'sort_order' => 10, 'tags' => ['рамка встречи']],
                ['client_key' => 'spin_discovery', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'СПИН-диагностика. S: какие регулярные маршруты и объёмы? Заполните {routes}, {volume_forecast}. P: что чаще всего ломается в перевозках? I: как это влияет на бюджет, сроки, производство, клиентов? N: какой результат через 1-2 месяца будет для вас заметным? Заполните критерии успеха — {decision_criteria}.', 'hint' => 'Говорите меньше клиента. Записывайте формулировки, которыми потом будете обосновывать КП.', 'sort_order' => 20, 'tags' => ['СПИН', 'диагностика'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
                ['client_key' => 'criteria_probe', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Выяснить must-have: документы, страхование, география, SLA, интеграция, отсрочка, тарифы, отчётность, требования к водителям/ТС. Спросить: «Что является стоп-фактором, без чего поставщик не проходит?» Заполните критерии выбора — {decision_criteria}.', 'hint' => 'Разделяйте обязательные критерии и пожелания. Это поможет не обещать лишнее.', 'sort_order' => 30, 'tags' => ['критерии'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'procedure_probe', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Разобрать процедуру входа: кто согласует, какие документы нужны, есть ли тендер/служба безопасности/юристы, сколько длится тест, как оценивают результат. Спросить: «Какой самый быстрый легальный путь к пилоту?»', 'hint' => 'Ищите не только ЛПР, но и процесс принятия решения.', 'sort_order' => 40, 'tags' => ['процедура входа']],
                ['client_key' => 'anti_criteria', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Спросить про негативный опыт: «Что точно нельзя повторить с новым перевозчиком? Какие ошибки подрядчиков раньше были критичными?» Затем проговорить: «Значит, в пилоте отдельно контролируем эти риски».', 'hint' => 'Антикритерии часто сильнее продают, чем преимущества.', 'sort_order' => 50, 'tags' => ['риски']],
                ['client_key' => 'proposal_frame', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Предложить формат: «Не предлагаю менять текущую схему целиком. Берём 1-2 маршрута, заранее фиксируем KPI: срок подачи, статусность, документы, отклонения, цена. Через 30 дней смотрим факт и принимаем решение».', 'hint' => 'Пилот с KPI превращает разговор из обещаний в проверку.', 'sort_order' => 60, 'tags' => ['пилот', 'KPI']],
                ['client_key' => 'objection_stall', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если клиент откладывает: «Окей, длинный разговор сейчас не нужен. Чтобы не потерять контекст, пришлю резюме на одну страницу: что услышал, где может быть польза, какой минимальный пилот. Когда вернуться к обсуждению?» Заполните дату — {next_step_date}.', 'hint' => 'Отложено без даты = потеряно. Добейтесь конкретного окна возврата.', 'sort_order' => 70, 'tags' => ['отложено'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'objection_competitor', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если есть текущий подрядчик: «Хорошо, значит процесс уже работает. Я не предлагаю ломать его. Предлагаю проверить резерв по одному критичному маршруту или закрыть пиковую нагрузку, чтобы у вас была страховка». Спросить: «Где резерв особенно нужен?»', 'hint' => 'Не атакуйте текущего поставщика. Продавайте резерв и снижение риска.', 'sort_order' => 75, 'tags' => ['конкурент', 'резерв']],
                ['client_key' => 'objection_price', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если спрашивают цену до вводных: «Назвать цифру сейчас можно, но она будет либо завышена, либо опасно низкая. Давайте возьмём 2-3 типовых маршрута, груз и условия — я дам вилку и поясню, что влияет на ставку». Заполните бюджетный ориентир — {budget_window}.', 'hint' => 'Цена без параметров — ловушка. Переводите в расчёт на одинаковых вводных.', 'sort_order' => 78, 'tags' => ['цена'], 'capture_field_codes' => ['budget_window']],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Закрыть встречу: «Итог: критерии выбора — {decision_criteria}; маршруты/объём — {routes}; следующий шаг — резюме и дорожная карта входа. Дата следующего контакта — {next_step_date}. Ответственные с вашей стороны: кто согласует документы и кто принимает решение?»', 'hint' => 'После встречи должна появиться задача или письмо-резюме, иначе встреча не монетизируется.', 'sort_order' => 80, 'tags' => ['итог', 'следующий шаг'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После встречи: занесите критерии, процедуру входа, блокеры, текущего подрядчика и дату следующего шага. Если согласован пилот — создайте задачу на КП/договор/проверку документов.', 'hint' => 'Финальная ценность сценария — чистая CRM-карточка и понятная дорожная карта.', 'sort_order' => 90, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'spin_discovery', 'reaction' => null, 'customer_label' => 'Да, такой формат подходит'],
                ['from' => 'spin_discovery', 'to' => 'criteria_probe', 'reaction' => 'positive_signal', 'customer_label' => 'Да, задавайте вопросы'],
                ['from' => 'spin_discovery', 'to' => 'procedure_probe', 'reaction' => 'need_info', 'customer_label' => 'Нужно уточнить у коллег'],
                ['from' => 'spin_discovery', 'to' => 'objection_stall', 'reaction' => 'stall', 'customer_label' => 'Сейчас не готов общаться, пишите на почту'],
                ['from' => 'spin_discovery', 'to' => 'objection_competitor', 'reaction' => 'competitor', 'customer_label' => 'У нас уже есть перевозчик'],
                ['from' => 'spin_discovery', 'to' => 'objection_price', 'reaction' => 'price_objection', 'customer_label' => 'Сначала скажите цену'],
                ['from' => 'criteria_probe', 'to' => 'procedure_probe', 'reaction' => null, 'customer_label' => 'Критерии понятны, дальше про процедуру'],
                ['from' => 'procedure_probe', 'to' => 'anti_criteria', 'reaction' => null, 'customer_label' => 'Процедуру описал, давайте про риски'],
                ['from' => 'anti_criteria', 'to' => 'proposal_frame', 'reaction' => null, 'customer_label' => 'Да, эти ошибки нельзя повторять'],
                ['from' => 'proposal_frame', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Пилот на 1-2 маршрута звучит разумно'],
                ['from' => 'objection_stall', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Хорошо, пришлите резюме и вернёмся'],
                ['from' => 'objection_competitor', 'to' => 'criteria_probe', 'reaction' => null, 'customer_label' => 'Резервный вариант можно обсудить'],
                ['from' => 'objection_price', 'to' => 'procedure_probe', 'reaction' => null, 'customer_label' => 'Окей, считайте по типовым маршрутам'],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, следующий шаг подтверждаю'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Растём в бюджете',
            description: 'Расширение действующего клиента: больше заказов, улучшение маржинальности и условий оплаты.',
            channel: 'meeting',
            tags: ['апсейл', 'удержание', 'бюджет', 'СПИН', 'НЛП'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: открыть разговор о росте без давления на клиента. Сказать: «Спасибо за текущую работу. Я хочу обсудить не просто больше заявок, а где мы можем снять нагрузку с вашей логистики: пиковые периоды, проблемные направления, документы, сроки и прогнозируемость».', 'hint' => 'Апсейл начинается с признания текущего результата, а не с просьбы «дайте больше объёма».', 'sort_order' => 10, 'tags' => ['апсейл', 'старт']],
                ['client_key' => 'spin_growth', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'СПИН по росту: S — какие маршруты/объёмы растут? Заполните {routes}, {volume_forecast}. P — где сейчас теряете время или деньги? I — как это влияет на планирование и бюджет? N — что будет признаком успешного расширения? Заполните критерии — {decision_criteria}.', 'hint' => 'Ищите экономику расширения: меньше простоев, меньше ручного контроля, стабильнее закрытие пиков.', 'sort_order' => 20, 'tags' => ['СПИН', 'рост объёма'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
                ['client_key' => 'growth_offer', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Предложить пакет роста: «Для расширения предлагаю не просто больше машин, а управляемую схему: закреплённые слоты, SLA по статусам, резерв перевозчиков, единая точка ответственности и еженедельный короткий отчёт по KPI».', 'hint' => 'Продавайте систему управления, а не отдельную перевозку.', 'sort_order' => 30, 'tags' => ['пакет', 'SLA']],
                ['client_key' => 'roi_reframe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если клиент спорит о цене: «Давайте считать не ставку в вакууме, а совокупную экономику: простои, штрафы, скорость закрытия документов, часы вашей команды на контроль, риски срыва. Если наша ставка выше, она должна окупаться этими параметрами. Давайте проверим на пилоте». Заполните бюджетный ориентир — {budget_window}.', 'hint' => 'Не обещайте «дешевле». Обосновывайте, где цена превращается в управляемость.', 'sort_order' => 40, 'tags' => ['цена', 'экономика'], 'capture_field_codes' => ['budget_window']],
                ['client_key' => 'terms_negotiation', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Обсудить условия оплаты: «При росте объёма нам важно согласовать график, который выдерживают обе стороны. Какой формат для вас реалистичен: предоплата, частичная предоплата, постоплата, лимит на период? Что должно быть в документах, чтобы оплата шла без задержек?» Заполните условия — {payment_terms}.', 'hint' => 'Не отдавайте условия оплаты без встречного обязательства: объём, срок, SLA, регулярность.', 'sort_order' => 50, 'tags' => ['условия оплаты'], 'capture_field_codes' => ['payment_terms']],
                ['client_key' => 'pilot_frame', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если клиент не готов расширять сразу: «Давайте не масштабировать вслепую. Берём пилот на 30 дней: объём {volume_forecast}, маршруты {routes}, KPI, условия оплаты {payment_terms}, дата ревью {next_step_date}. После факта решаем, расширяем или корректируем».', 'hint' => 'Пилот должен иметь объём, сроки, метрики и дату ревью. Без этого это просто разговор.', 'sort_order' => 60, 'tags' => ['пилот', 'ревью'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Зафиксировать договорённости: объём пилота — {volume_forecast}; маршруты — {routes}; условия оплаты — {payment_terms}; KPI — {decision_criteria}; дата ревью — {next_step_date}. Сказать: «Я отправлю резюме и проект условий, чтобы мы одинаково понимали рамки».', 'hint' => 'Закрепляйте рост письменно в тот же день.', 'sort_order' => 70, 'tags' => ['договорённости'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После разговора: обновите карточку клиента, создайте задачу на КП/допсоглашение/ревью пилота, отметьте аргумент, который сработал: SLA, резерв, экономика, документы или условия оплаты.', 'hint' => 'Апсейл должен завершаться управляемым следующим шагом, а не «подумают».', 'sort_order' => 80, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'spin_growth', 'reaction' => null, 'customer_label' => 'Да, можно обсудить расширение'],
                ['from' => 'spin_growth', 'to' => 'growth_offer', 'reaction' => 'positive_signal', 'customer_label' => 'Объём растёт, нужен более стабильный процесс'],
                ['from' => 'spin_growth', 'to' => 'roi_reframe', 'reaction' => 'price_objection', 'customer_label' => 'При большем объёме нам нужна ниже ставка'],
                ['from' => 'spin_growth', 'to' => 'terms_negotiation', 'reaction' => 'need_info', 'customer_label' => 'Нужно согласовать условия оплаты'],
                ['from' => 'spin_growth', 'to' => 'pilot_frame', 'reaction' => 'stall', 'customer_label' => 'Пока не готовы резко увеличивать объём'],
                ['from' => 'spin_growth', 'to' => 'pilot_frame', 'reaction' => 'competitor', 'customer_label' => 'Часть объёма уже отдали другому подрядчику'],
                ['from' => 'growth_offer', 'to' => 'terms_negotiation', 'reaction' => null, 'customer_label' => 'Интересно, давайте обсудим условия'],
                ['from' => 'roi_reframe', 'to' => 'terms_negotiation', 'reaction' => null, 'customer_label' => 'Окей, считаем экономику и условия'],
                ['from' => 'terms_negotiation', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Такой график оплаты можно согласовать'],
                ['from' => 'pilot_frame', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Давайте начнём с пилота на 30 дней'],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, договорённости подтверждаю'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Тренажёр: короткий звонок (цена, срок, документы)',
            description: 'Уплотнённый сценарий для прогона тренажёра: те же ветки реакций, тексты с лексикой под подсказки «по теме диалога».',
            channel: 'phone',
            tags: ['тренажёр', 'цена', 'срок', 'документы'],
            entryNodeKey: 'trainer_intro',
            nodes: [
                ['client_key' => 'trainer_intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка: откройте звонок и получите право на вопросы. Сказать: «Добрый день. Чтобы рассчитать перевозку без ошибки, уточню маршрут, груз, дату готовности и требования к машине. Это займёт пару минут. Начнём?» Ваша задача — не назвать ставку, а собрать вводные.', 'hint' => 'Оценка шага: менеджер не перебивает, задаёт рамку и не обещает цену без данных.', 'sort_order' => 10, 'tags' => ['тренажёр', 'старт']],
                ['client_key' => 'trainer_qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Тренировка реакции. Спросите: маршрут {route_from} — {route_to}, груз {cargo_type}, дата готовности {loading_date}, дедлайн решения {decision_deadline}. Затем выберите реакцию клиента: позитив, цена, нужны детали, откладывает, конкурент.', 'hint' => 'Следите за ключевыми словами клиента: «дорого», «есть перевозчик», «пришлите на почту», «нужно срочно», «нет документов».', 'sort_order' => 20, 'tags' => ['квалификация', 'тренажёр'], 'capture_field_codes' => ['route_from', 'route_to', 'cargo_type', 'loading_date', 'decision_deadline']],
                ['client_key' => 'trainer_price', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка возражения «дорого». Правильная структура: признать → разложить услугу → спросить критерий. Сказать: «Понимаю, ставка важна. Сравним одинаково: срок подачи, страхование, контроль статусов, документы и ответственность за срыв. Что для вас критичнее всего сохранить?» Заполните критерий — {decision_criteria}.', 'hint' => 'Ошибка: защищать цену фразой «у нас качество». Нужно показать состав риска.', 'sort_order' => 30, 'tags' => ['цена', 'тренажёр'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'trainer_positive', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка позитивной ветки. Сказать: «Отлично, вводных достаточно. Я подготовлю КП: ставка, что включено, срок подачи, документы, ответственный. Куда отправить и до какого времени вам нужно решение?» Заполните почту — {email}.', 'hint' => 'Позитивный клиент тоже требует фиксации дедлайна и канала связи.', 'sort_order' => 40, 'tags' => ['КП', 'тренажёр'], 'capture_field_codes' => ['email']],
                ['client_key' => 'trainer_need_docs', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка ветки «нужны данные». Сказать: «Чтобы расчёт не пришлось переделывать, мне нужны: точные адреса, параметры груза, требования к авто, контакт на погрузке и документы по грузу. Я отправлю список и после получения вернусь с расчётом». Заполните канал — {email}.', 'hint' => 'Называйте конкретный список документов, а не «пришлите всё».', 'sort_order' => 50, 'tags' => ['документы', 'тренажёр'], 'capture_field_codes' => ['email']],
                ['client_key' => 'trainer_wrap', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка завершения. Сказать: «Подытожу: маршрут {route_from} — {route_to}, груз {cargo_type}, готовность {loading_date}, следующий шаг — КП/документы/повторный звонок. Если что-то меняется — сразу корректируем». Попросите клиента подтвердить.', 'hint' => 'Хороший финал — клиент слышит, что вы записали, и понимает следующий шаг.', 'sort_order' => 60, 'tags' => ['итог', 'тренажёр']],
                ['client_key' => 'trainer_end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После тренировки: отметьте исход, главное возражение и одну ошибку/сильную сторону менеджера. Если это реальный разговор — создайте задачу на КП, документы или повторное касание.', 'hint' => 'Сценарий тренажёра должен завершаться коротким разбором качества диалога.', 'sort_order' => 70, 'tags' => ['разбор', 'завершение']],
            ],
            transitions: [
                ['from' => 'trainer_intro', 'to' => 'trainer_qualify', 'reaction' => null, 'customer_label' => 'Да, давайте быстро уточним'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_price', 'reaction' => 'price_objection', 'customer_label' => 'Дорого, мне нужна ставка ниже'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_positive', 'reaction' => 'positive_signal', 'customer_label' => 'Хорошо, данных достаточно, считайте'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_need_docs', 'reaction' => 'need_info', 'customer_label' => 'Какие документы и данные вам нужны?'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_price', 'reaction' => 'stall', 'customer_label' => 'Не срочно, просто прицениваемся'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_price', 'reaction' => 'competitor', 'customer_label' => 'У нас уже есть предложение от другого'],
                ['from' => 'trainer_price', 'to' => 'trainer_wrap', 'reaction' => null, 'customer_label' => 'Понял, сравним состав услуги'],
                ['from' => 'trainer_positive', 'to' => 'trainer_wrap', 'reaction' => null, 'customer_label' => 'Жду КП на почту'],
                ['from' => 'trainer_need_docs', 'to' => 'trainer_wrap', 'reaction' => null, 'customer_label' => 'Пришлите список, подготовим'],
                ['from' => 'trainer_wrap', 'to' => 'trainer_end', 'reaction' => null, 'customer_label' => 'Да, всё верно'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Дожим КП после отправки',
            description: 'Контроль после коммерческого предложения: выяснить статус, снять возражение и вернуть клиента к следующему шагу.',
            channel: 'phone',
            tags: ['КП', 'дожим', 'follow-up', 'возражения'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: не спрашивать «ну что решили?», а вернуть клиента к критериям выбора. Сказать: «Добрый день. Отправлял КП по перевозке. Хочу быстро сверить: дошло ли предложение, всё ли понятно по ставке/сроку/документам и что мешает принять решение?» Заполните дату следующего шага — {next_step_date}.', 'hint' => 'Не начинайте с давления. Сначала выясните статус КП и реальный блокер.', 'sort_order' => 10, 'tags' => ['старт', 'КП'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'status_check', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Уточнить статус: КП посмотрели? Кто принимает решение? С чем сравнивают? Что должно быть в предложении, чтобы его согласовали? Зафиксируйте критерии — {decision_criteria}, бюджет/целевую ставку — {budget_window}, почту/канал — {email}.', 'hint' => 'Если клиент молчит, чаще всего есть один из блокеров: цена, конкурент, нет ЛПР, нет срочности, не хватает данных.', 'sort_order' => 20, 'tags' => ['статус КП', 'ветвление'], 'capture_field_codes' => ['decision_criteria', 'budget_window', 'email']],
                ['client_key' => 'price_reframe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Ответ на цену: «Понимаю. Давайте сравним КП по одинаковым условиям: срок подачи, тип машины, страхование, документы, ответственность за срыв и отсрочка. Если нужно снизить ставку, покажу, за счёт какого условия это возможно и какой риск появляется».', 'hint' => 'Не торгуйтесь вслепую. Любая уступка должна быть обменом: объём, предоплата, регулярность, упрощение требований.', 'sort_order' => 30, 'tags' => ['цена', 'дожим']],
                ['client_key' => 'competitor_probe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если сравнивают с конкурентом: «Окей, это нормальная практика. Подскажите, по какому пункту мы проигрываем: ставка, срок, отсрочка, документы, опыт на маршруте? Я не буду спорить, просто пойму, можем ли честно усилить предложение». Зафиксируйте текущего подрядчика — {current_provider}.', 'hint' => 'Ваша цель — узнать критерий проигрыша, а не ругать конкурента.', 'sort_order' => 40, 'tags' => ['конкурент'], 'capture_field_codes' => ['current_provider']],
                ['client_key' => 'decision_path', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Если решение зависло: спросить «Кто ещё участвует в согласовании? Что им важно увидеть? Когда реалистично вернуться к решению?» Заполните дату следующего шага — {next_step_date}. Если нужен руководитель/закупка — предложите короткое резюме для пересылки.', 'hint' => 'Дожим без понимания цепочки согласования превращается в повторные звонки «как дела?».', 'sort_order' => 50, 'tags' => ['согласование'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'close_next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Закрыть разговор конкретикой: «Фиксирую: блокер — {decision_criteria}, целевой ориентир — {budget_window}, следующий шаг — уточнённое КП/созвон/документы, дата — {next_step_date}. Я отправлю обновлённое резюме и вернусь в согласованное время».', 'hint' => 'Следующий шаг должен быть измеримым: обновить КП, согласовать ставку, получить документы, назначить созвон.', 'sort_order' => 60, 'tags' => ['следующий шаг'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После звонка: обновите статус лида, запишите главный блокер, создайте задачу на повторное касание или обновление КП. Если клиент отказался — сохраните причину: цена, конкурент, сроки, документы, нет потребности.', 'hint' => 'Дожим КП полезен только если причина решения попадает обратно в CRM.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'status_check', 'reaction' => null, 'customer_label' => 'Да, КП видел, давайте сверим'],
                ['from' => 'status_check', 'to' => 'close_next_step', 'reaction' => 'positive_signal', 'customer_label' => 'В целом подходит, нужно финализировать'],
                ['from' => 'status_check', 'to' => 'price_reframe', 'reaction' => 'price_objection', 'customer_label' => 'Ставка выше, чем мы ожидали'],
                ['from' => 'status_check', 'to' => 'decision_path', 'reaction' => 'need_info', 'customer_label' => 'Нужно согласовать с руководителем/закупкой'],
                ['from' => 'status_check', 'to' => 'decision_path', 'reaction' => 'stall', 'customer_label' => 'Пока не смотрели, вернитесь позже'],
                ['from' => 'status_check', 'to' => 'competitor_probe', 'reaction' => 'competitor', 'customer_label' => 'Есть предложение от другого перевозчика'],
                ['from' => 'price_reframe', 'to' => 'close_next_step', 'reaction' => null, 'customer_label' => 'Хорошо, пришлите вариант с пояснением'],
                ['from' => 'competitor_probe', 'to' => 'close_next_step', 'reaction' => null, 'customer_label' => 'Сравните по этим условиям и вернитесь'],
                ['from' => 'decision_path', 'to' => 'close_next_step', 'reaction' => null, 'customer_label' => 'Давайте вернёмся в согласованную дату'],
                ['from' => 'close_next_step', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, следующий шаг подтверждаю'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Тендер / закупщик',
            description: 'Разговор с закупкой или тендерным клиентом: формальные критерии, пакет документов, цена и путь к пилоту.',
            channel: 'meeting',
            tags: ['тендер', 'закупка', 'документы', 'цена'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: признать формальный процесс и не пытаться «продавить» закупщика. Сказать: «Понимаю, что у вас есть регламент выбора подрядчиков. Чтобы не тратить ваше время, уточню критерии, обязательный пакет документов, сроки тендера и где мы можем быть конкурентны».', 'hint' => 'С закупкой работает язык критериев, рисков и соответствия требованиям.', 'sort_order' => 10, 'tags' => ['старт', 'закупка']],
                ['client_key' => 'requirements_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Спросить: какие маршруты и объёмы в тендере — {routes}, {volume_forecast}; какие документы обязательны; какая отсрочка/форма оплаты — {payment_terms}; какой дедлайн подачи — {decision_deadline}; какие критерии кроме цены — {decision_criteria}.', 'hint' => 'Соберите матрицу критериев до обсуждения ставки.', 'sort_order' => 20, 'tags' => ['требования', 'тендер'], 'capture_field_codes' => ['routes', 'volume_forecast', 'payment_terms', 'decision_deadline', 'decision_criteria']],
                ['client_key' => 'document_package', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если нужны документы: «Подготовим пакет по вашему чек-листу: реквизиты, договор, страхование, парк/партнёры, опыт по маршрутам, регламенты статусов и закрывающие документы. Пришлите форму или список требований, чтобы не гадать». Заполните канал — {email}.', 'hint' => 'Не отправляйте «общую презентацию», если у закупки есть конкретный чек-лист.', 'sort_order' => 30, 'tags' => ['документы'], 'capture_field_codes' => ['email']],
                ['client_key' => 'price_matrix', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если давят ценой: «Мы можем дать ставку по каждому маршруту, но прошу фиксировать одинаковые условия: срок подачи, простой, документы, страхование, отсрочка, штрафы. Если цена — главный критерий, покажем базовый вариант и вариант с повышенным SLA». Заполните бюджетный ориентир — {budget_window}.', 'hint' => 'Разделяйте базовую ставку и ставку с сервисными обязательствами.', 'sort_order' => 40, 'tags' => ['цена', 'матрица'], 'capture_field_codes' => ['budget_window']],
                ['client_key' => 'incumbent_competitor', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если есть текущий подрядчик: «Логично, менять работающего поставщика рискованно. Мы можем войти как резерв или пилот на проблемном направлении, чтобы вы сравнили факт без риска для основной схемы». Заполните текущего подрядчика — {current_provider}.', 'hint' => 'В тендерах часто выигрывает не «лучший вообще», а тот, кто снижает риск закупки.', 'sort_order' => 50, 'tags' => ['конкурент', 'резерв'], 'capture_field_codes' => ['current_provider']],
                ['client_key' => 'pilot_or_submission', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Закрыть на процесс: «Что будет корректным следующим шагом: подача полного пакета, расчёт по матрице маршрутов или пилот на одном направлении? Кто принимает финальное решение и когда?» Заполните дату следующего шага — {next_step_date}.', 'hint' => 'Всегда переводите закупочный разговор в календарь и список артефактов.', 'sort_order' => 60, 'tags' => ['следующий шаг'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После разговора: создайте задачу на пакет документов/матрицу ставок/пилот, приложите требования закупки и дедлайн. В заметке отдельно укажите: критерии, дедлайн, текущего поставщика, форму оплаты и лицо принятия решения.', 'hint' => 'Тендер без чек-листа, дедлайна и ответственного быстро теряется.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'requirements_probe', 'reaction' => null, 'customer_label' => 'Да, расскажу требования и регламент'],
                ['from' => 'requirements_probe', 'to' => 'pilot_or_submission', 'reaction' => 'positive_signal', 'customer_label' => 'Вы можете участвовать, готовьте предложение'],
                ['from' => 'requirements_probe', 'to' => 'price_matrix', 'reaction' => 'price_objection', 'customer_label' => 'Победит тот, у кого будет ниже цена'],
                ['from' => 'requirements_probe', 'to' => 'document_package', 'reaction' => 'need_info', 'customer_label' => 'Сначала пришлите пакет документов'],
                ['from' => 'requirements_probe', 'to' => 'pilot_or_submission', 'reaction' => 'stall', 'customer_label' => 'Тендер позже, сейчас собираем участников'],
                ['from' => 'requirements_probe', 'to' => 'incumbent_competitor', 'reaction' => 'competitor', 'customer_label' => 'У нас есть действующий перевозчик'],
                ['from' => 'document_package', 'to' => 'pilot_or_submission', 'reaction' => null, 'customer_label' => 'Пакет документов нужен до дедлайна'],
                ['from' => 'price_matrix', 'to' => 'pilot_or_submission', 'reaction' => null, 'customer_label' => 'Дайте две ставки: базовую и с SLA'],
                ['from' => 'incumbent_competitor', 'to' => 'pilot_or_submission', 'reaction' => null, 'customer_label' => 'Резервный пилот можно рассмотреть'],
                ['from' => 'pilot_or_submission', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Следующий шаг и дедлайн подтверждаю'],
            ],
            reactionIds: $reactionIds,
        );
    }

    /**
     * @return array<string, int>
     */
    private function seedReactionClasses(): array
    {
        $reactions = [
            ['key' => 'positive_signal', 'label' => 'Клиент позитивен / готов к следующему шагу', 'sort_order' => 10],
            ['key' => 'price_objection', 'label' => 'Возражение по цене', 'sort_order' => 20],
            ['key' => 'need_info', 'label' => 'Нужны дополнительные данные', 'sort_order' => 30],
            ['key' => 'stall', 'label' => 'Откладывает решение', 'sort_order' => 40],
            ['key' => 'competitor', 'label' => 'Сравнивает с другим перевозчиком', 'sort_order' => 50],
        ];

        $reactionIds = [];

        foreach ($reactions as $row) {
            $model = SalesScriptReactionClass::query()->updateOrCreate(
                ['key' => $row['key']],
                ['label' => $row['label'], 'sort_order' => $row['sort_order']],
            );

            $reactionIds[$row['key']] = $model->id;
        }

        return $reactionIds;
    }

    private function seedCaptureFields(): void
    {
        $fields = [
            ['code' => 'client_name', 'label' => 'Имя собеседника', 'sort_order' => 10],
            ['code' => 'routes', 'label' => 'Маршруты', 'sort_order' => 20],
            ['code' => 'cargo_type', 'label' => 'Тип груза', 'sort_order' => 30],
            ['code' => 'route_from', 'label' => 'Откуда груз', 'sort_order' => 40],
            ['code' => 'route_to', 'label' => 'Куда груз', 'sort_order' => 50],
            ['code' => 'loading_date', 'label' => 'Дата готовности груза', 'sort_order' => 60],
            ['code' => 'decision_deadline', 'label' => 'Дедлайн решения клиента', 'sort_order' => 70],
            ['code' => 'email', 'label' => 'Почта или канал для КП', 'sort_order' => 80],
            ['code' => 'current_provider', 'label' => 'Текущий перевозчик / подрядчик', 'sort_order' => 90],
            ['code' => 'decision_criteria', 'label' => 'Критерии выбора клиента', 'sort_order' => 100],
            ['code' => 'budget_window', 'label' => 'Бюджетный ориентир / ставка клиента', 'sort_order' => 110],
            ['code' => 'next_step_date', 'label' => 'Дата следующего шага', 'sort_order' => 120],
            ['code' => 'volume_forecast', 'label' => 'Планируемый объём', 'sort_order' => 130],
            ['code' => 'payment_terms', 'label' => 'Условия оплаты', 'sort_order' => 140],
        ];

        foreach ($fields as $field) {
            SalesScriptCaptureField::query()->updateOrCreate(
                ['code' => $field['code']],
                [
                    'label' => $field['label'],
                    'value_type' => 'text',
                    'sort_order' => $field['sort_order'],
                ],
            );
        }
    }

    /**
     * @param  list<array{client_key:string,kind:SalesScriptNodeKind,body:string,hint:?string,sort_order:int,canvas_x?:int,canvas_y?:int,tags?:list<string>,capture_field_codes?:list<string>}>  $nodes
     * @param  list<array{from:string,to:string,reaction:?string,customer_label?:?string}>  $transitions
     * @param  array<string, int>  $reactionIds
     */
    private function seedScript(
        string $title,
        string $description,
        string $channel,
        array $tags,
        string $entryNodeKey,
        array $nodes,
        array $transitions,
        array $reactionIds,
    ): void {
        $script = SalesScript::query()->firstOrCreate(
            ['title' => $title],
            [
                'description' => $description,
                'channel' => $channel,
                'tags' => $tags,
            ],
        );

        $script->update([
            'description' => $description,
            'channel' => $channel,
            'tags' => $tags,
        ]);

        $version = SalesScriptVersion::query()->firstOrCreate(
            [
                'sales_script_id' => $script->id,
                'version_number' => 1,
            ],
            [
                'published_at' => Carbon::now(),
                'is_active' => true,
                'entry_node_key' => $entryNodeKey,
            ],
        );

        $version->update([
            'published_at' => Carbon::now(),
            'is_active' => true,
            'entry_node_key' => $entryNodeKey,
        ]);

        $nodeIds = [];

        foreach ($nodes as $nodePayload) {
            $node = SalesScriptNode::query()->updateOrCreate(
                [
                    'sales_script_version_id' => $version->id,
                    'client_key' => $nodePayload['client_key'],
                ],
                [
                    'kind' => $nodePayload['kind'],
                    'body' => $nodePayload['body'],
                    'hint' => $nodePayload['hint'],
                    'sort_order' => $nodePayload['sort_order'],
                    'canvas_x' => $nodePayload['canvas_x'] ?? null,
                    'canvas_y' => $nodePayload['canvas_y'] ?? null,
                    'tags' => $nodePayload['tags'] ?? [],
                    'capture_field_codes' => $nodePayload['capture_field_codes'] ?? [],
                ],
            );

            $nodeIds[$nodePayload['client_key']] = $node->id;
        }

        SalesScriptTransition::query()
            ->where('sales_script_version_id', $version->id)
            ->delete();

        foreach ($transitions as $transitionPayload) {
            SalesScriptTransition::query()->create([
                'sales_script_version_id' => $version->id,
                'from_node_id' => $nodeIds[$transitionPayload['from']],
                'to_node_id' => $nodeIds[$transitionPayload['to']],
                'sales_script_reaction_class_id' => $transitionPayload['reaction'] !== null
                    ? $reactionIds[$transitionPayload['reaction']]
                    : null,
                'customer_label' => $transitionPayload['customer_label'] ?? null,
                'sort_order' => 0,
            ]);
        }
    }
}
