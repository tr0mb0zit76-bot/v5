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
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: получить право задать короткие вопросы и не гадать со ставкой. Сказать: «Добрый день. Вы оставляли запрос на перевозку. Чтобы я не считал вслепую, уточню маршрут, груз, дату, машину и когда вам нужен ответ. Пару минут есть?» Если клиент торопится — спросите только маршрут и дату, остальное доберите позже.', 'hint' => 'Не называйте ставку до вводных. Сначала спокойно соберите минимум.', 'sort_order' => 10, 'tags' => ['старт', 'рамка']],
                ['client_key' => 'qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: собрать данные для расчёта. Спросить по одному: имя — {client_name}, маршрут {route_from} — {route_to}, груз — {cargo_type}, дата готовности — {loading_date}, когда нужно решение — {decision_deadline}. В конце сказать: «Понял, теперь могу выбрать нормальный следующий шаг: считать, уточнить недостающие данные или поставить паузу».', 'hint' => 'Если клиент просит цену сразу, верните разговор к вводным: без них ставка будет случайной.', 'sort_order' => 20, 'tags' => ['квалификация', 'данные для ставки'], 'capture_field_codes' => ['client_name', 'route_from', 'route_to', 'cargo_type', 'loading_date', 'decision_deadline']],
                ['client_key' => 'price_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если клиент давит на цену: «Понимаю, цена важна. Давайте сравнивать по одинаковым условиям: срок подачи, машина, документы, простой и кто отвечает, если что-то сорвётся. Что для вас точно нельзя потерять?» Зафиксируйте критерий — {decision_criteria}.', 'hint' => 'Не оправдывайте цену. Спросите, что клиент сравнивает и чем нельзя рисковать.', 'sort_order' => 30, 'tags' => ['возражение', 'цена'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'need_info', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если данных не хватает: «Чтобы расчёт не пришлось переделывать, мне нужны точные адреса, груз, дата, требования к машине и контакт на погрузке. Я пришлю короткий список и после ответа вернусь с расчётом». Уточните канал — {email}.', 'hint' => 'Называйте конкретный список, а не просите “пришлите всё”.', 'sort_order' => 40, 'tags' => ['нужны данные'], 'capture_field_codes' => ['email']],
                ['client_key' => 'positive', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если данных достаточно: «Отлично, {client_name}. Фиксирую маршрут {route_from} — {route_to}, груз {cargo_type}, готовность {loading_date}. Подготовлю КП. Куда отправить и кто будет принимать решение?» Заполните канал/почту — {email}.', 'hint' => 'Заканчивайте конкретикой: куда отправить, когда вернуться, кто принимает решение.', 'sort_order' => 50, 'tags' => ['КП', 'следующий шаг'], 'capture_field_codes' => ['email']],
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
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: быстро понять, с кем говорить по перевозкам. Сказать: «Добрый день. Подскажите, кто у вас отвечает за логистику и выбор перевозчиков? Хочу коротко понять, можем ли быть полезны по срокам, резерву машин или документам».', 'hint' => 'Не начинайте с презентации. Сначала найдите ответственного или корректный путь к нему.', 'sort_order' => 10, 'tags' => ['старт', 'выход на ЛПР']],
                ['client_key' => 'gatekeeper_branch', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: спокойно объяснить повод звонка. Сказать: «Вопрос короткий: кто у вас занимается перевозками и с кем можно сверить пару моментов по срокам, резерву машин или документам?» Если не соединяют — попросите имя, должность, почту и удобное время для повторного контакта.', 'hint' => 'Через секретаря не продаём. Нужен маршрут к ЛПР или разрешение написать.', 'sort_order' => 20, 'tags' => ['секретарь', 'ветвление']],
                ['client_key' => 'clarify_contact', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Спросить: «Как зовут ответственного за перевозки? Какая должность? Куда лучше отправить короткое письмо? Когда удобнее вернуться к вопросу?» Заполните имя — {client_name}, канал/почту — {email}, дату следующего касания — {next_step_date}.', 'hint' => 'Просите не просто телефон, а корректный контакт и время.', 'sort_order' => 30, 'tags' => ['контакт', 'следующее касание'], 'capture_field_codes' => ['client_name', 'email', 'next_step_date']],
                ['client_key' => 'lpr_open', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: договориться на короткие вопросы. Сказать ЛПР: «Чтобы не рассказывать лишнего, задам несколько вопросов по перевозкам. Если пользы не будет — так и скажем. Если будет точка для работы — обсудим следующий шаг».', 'hint' => 'Короткая диагностика звучит проще, чем продажа.', 'sort_order' => 40, 'tags' => ['ЛПР', 'рамка']],
                ['client_key' => 'spin_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: понять текущую ситуацию. Спросить: «Какие направления и объёмы у вас повторяются?» — {routes}, {volume_forecast}. Потом: «Где чаще всего бывают сложности: машина, срок, документы, связь?» И: «Что должно измениться, чтобы вы сказали, что стало лучше?» — {decision_criteria}.', 'hint' => 'Слушайте, что клиент сам называет проблемой. По этому выбирается следующая ветка.', 'sort_order' => 50, 'tags' => ['СПИН', 'диагностика'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
                ['client_key' => 'value_pitch', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если проблема понятна: «Тогда можно начать с одного маршрута или одной задачи: срок подачи, документы, статусы или резервная машина. Проверим на небольшом объёме и посмотрим по факту». Затем предложите короткий разбор.', 'hint' => 'Говорите про конкретную пользу, а не про все услуги сразу.', 'sort_order' => 60, 'tags' => ['ценность', 'пилот']],
                ['client_key' => 'soft_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Ответ на «не актуально / уже работаем / дорого»: «Понял. Уточню, чтобы не беспокоить вас не по делу: сейчас перевозок нет, всё закрывает текущий подрядчик или вопрос просто не в приоритете?» После ответа выберите следующий шаг.', 'hint' => 'Сначала выясните тип отказа. Не закрывайте разговор датой раньше времени.', 'sort_order' => 70, 'tags' => ['возражение', 'снижение риска']],
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
                ['client_key' => 'spin_discovery', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: выяснить карту процесса. Спросить: «Какие маршруты и объёмы повторяются?» — {routes}, {volume_forecast}. «Что чаще всего ломается?» «Во что это обходится?» «Какой результат через 1-2 месяца будет заметным?» Зафиксировать критерии успеха — {decision_criteria}.', 'hint' => 'Не показывайте будущий пилот раньше времени. Сейчас нужно услышать процесс клиента и его критерии, чтобы следующий шаг был уместным.', 'sort_order' => 20, 'tags' => ['СПИН', 'диагностика'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
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
                ['client_key' => 'spin_growth', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: найти экономику расширения. Спросить: «Какие маршруты или объёмы растут?» — {routes}, {volume_forecast}. «Где сейчас теряете время или деньги?» «Как это влияет на планирование и бюджет?» «Что будет признаком успешного расширения?» — {decision_criteria}.', 'hint' => 'Не просите больше объёма. Докажите, что расширение снимает конкретную нагрузку: пики, простои, ручной контроль, документы.', 'sort_order' => 20, 'tags' => ['СПИН', 'рост объёма'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
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
                ['client_key' => 'trainer_qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход тренировки: собрать вводные без ставки. Спросить: маршрут {route_from} — {route_to}, груз {cargo_type}, дата готовности {loading_date}, дедлайн решения {decision_deadline}. После ответа клиента тренажёр сам поведёт в ветку: цена, документы, пауза, конкурент или готовность считать.', 'hint' => 'Слушайте ключевые слова клиента, но не показывайте ему меню веток. Менеджер должен продолжать разговор естественно.', 'sort_order' => 20, 'tags' => ['квалификация', 'тренажёр'], 'capture_field_codes' => ['route_from', 'route_to', 'cargo_type', 'loading_date', 'decision_deadline']],
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
            title: 'Возврат уснувшего лида',
            description: 'Повторное касание после паузы: вернуть контекст, выяснить блокер и договориться о конкретном следующем шаге.',
            channel: 'phone',
            tags: ['реактивация', 'follow-up', 'лиды', 'КП'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: вернуться к старому обсуждению без давления. Сказать: «Добрый день. Возвращаюсь к нашему разговору по перевозке/КП. Хочу понять, вопрос ещё актуален, вводные изменились или лучше поставить паузу до конкретной даты?» Заполните дату следующего шага — {next_step_date}.', 'hint' => 'Не спрашивайте “ну что решили?”. Сначала выясните текущий статус.', 'sort_order' => 10, 'tags' => ['старт', 'реактивация'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'status_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: понять, почему лид остановился. Спросить: «На каком этапе вопрос: ещё выбираете, ждёте согласование, выбрали другого или перевозку отложили?» Зафиксировать блокер — {decision_criteria}, текущего подрядчика — {current_provider}, дедлайн решения — {decision_deadline}.', 'hint' => 'Не дожимайте вслепую. Сначала поймите причину паузы.', 'sort_order' => 20, 'tags' => ['статус', 'блокер'], 'capture_field_codes' => ['decision_criteria', 'current_provider', 'decision_deadline']],
                ['client_key' => 'no_urgency', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если вопрос отложен: «Понял. Тогда сейчас не отвлекаю. Когда тема снова появится: по дате, сезону, объёму или новому маршруту?» Заполните дату — {next_step_date}.', 'hint' => 'Пауза полезна только если понятна дата или событие возврата.', 'sort_order' => 30, 'tags' => ['отложено'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'competitor_lost', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если выбрали другого: «Понял, спасибо. Подскажите, по какому пункту выбрали их: ставка, срок, документы, отсрочка или опыт на маршруте? Хочу понять, можем ли быть запасным вариантом дальше». Зафиксируйте причину — {decision_criteria}.', 'hint' => 'Не спорьте с уже принятым решением. Узнайте критерий и оставьте право на резерв.', 'sort_order' => 40, 'tags' => ['конкурент', 'проигрыш'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'revise_offer', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если клиент готов вернуться: «Тогда обновим вводные: маршрут {route_from} — {route_to}, груз {cargo_type}, дата готовности {loading_date}, ориентир по ставке {budget_window}. После этого пришлю актуальный вариант и отмечу, что поменялось».', 'hint' => 'Старое КП почти всегда устаревает. Пересоберите факты.', 'sort_order' => 50, 'tags' => ['обновить КП'], 'capture_field_codes' => ['route_from', 'route_to', 'cargo_type', 'loading_date', 'budget_window']],
                ['client_key' => 'close_next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Закрыть конкретикой: «Фиксирую статус: {decision_criteria}. Следующий шаг — обновить КП, созвониться или вернуться к дате {next_step_date}. Я поставлю задачу и вернусь в это время».', 'hint' => 'После звонка должна появиться задача, иначе лид снова потеряется.', 'sort_order' => 60, 'tags' => ['следующий шаг', 'CRM'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После разговора: обновите статус лида, причину паузы/проигрыша, дату следующего контакта и источник решения. Если клиент выбрал конкурента — отметьте, по какому критерию проиграли, и создайте задачу на резервное касание.', 'hint' => 'Реактивация ценна не только сделкой, но и чистой причиной потери/паузы в CRM.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'status_probe', 'reaction' => null, 'customer_label' => 'Да, вопрос ещё можно обсудить'],
                ['from' => 'status_probe', 'to' => 'revise_offer', 'reaction' => 'positive_signal', 'customer_label' => 'Да, давайте обновим предложение'],
                ['from' => 'status_probe', 'to' => 'no_urgency', 'reaction' => 'stall', 'customer_label' => 'Сейчас не актуально, вернёмся позже'],
                ['from' => 'status_probe', 'to' => 'competitor_lost', 'reaction' => 'competitor', 'customer_label' => 'Мы уже выбрали другого подрядчика'],
                ['from' => 'status_probe', 'to' => 'revise_offer', 'reaction' => 'price_objection', 'customer_label' => 'Если сможете пересмотреть ставку, обсудим'],
                ['from' => 'status_probe', 'to' => 'revise_offer', 'reaction' => 'need_info', 'customer_label' => 'Нужно заново уточнить вводные'],
                ['from' => 'no_urgency', 'to' => 'close_next_step', 'reaction' => null, 'customer_label' => 'Хорошо, вернитесь в согласованную дату'],
                ['from' => 'competitor_lost', 'to' => 'close_next_step', 'reaction' => null, 'customer_label' => 'Причину понял, резервный контакт возможен'],
                ['from' => 'revise_offer', 'to' => 'close_next_step', 'reaction' => null, 'customer_label' => 'Жду обновлённый вариант'],
                ['from' => 'close_next_step', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, следующий шаг зафиксирован'],
            ],
            reactionIds: $reactionIds,
        );

        $objectionHandlingVersion = $this->seedScript(
            title: 'Преодоление возражений',
            description: 'Переиспользуемый сценарий для типовых возражений: не требуется, уже работаем, дорого, напишите позже.',
            channel: 'phone',
            tags: ['возражения', 'переиспользуемый', 'диагностика'],
            entryNodeKey: 'classify_objection',
            nodes: [
                ['client_key' => 'classify_objection', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: понять, какое именно возражение прозвучало. Сказать: «Понял. Чтобы не отвечать мимо, уточню: сейчас в перевозках совсем нет потребности, всё закрывает текущий подрядчик, вопрос в цене или просто не время?» Зафиксируйте причину — {decision_criteria}.', 'hint' => 'Не спорьте с первым “нет”. Сначала классифицируйте отказ.', 'sort_order' => 10, 'tags' => ['старт', 'классификация'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'no_need_probe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если говорят «не требуется»: «Понял. Тогда коротко: это потому что сейчас нет перевозок, всё закрыто внутри или работает текущий подрядчик? Что должно измениться, чтобы тема снова стала актуальной?» Заполните критерий — {decision_criteria}.', 'hint' => 'Цель — отделить реальное отсутствие потребности от скрытого “не вижу смысла”.', 'sort_order' => 20, 'tags' => ['нет потребности'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'competitor_probe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если уже работают с другим: «Хорошо, значит процесс закрыт. А что в текущей работе вас устраивает больше всего? И где обычно нужен запасной вариант: срочная подача, пиковая нагрузка, документы, цена?» Заполните текущего подрядчика — {current_provider} и критерий — {decision_criteria}.', 'hint' => 'Не атакуйте подрядчика. Ищите резервную роль или критерий для будущего входа.', 'sort_order' => 30, 'tags' => ['конкурент', 'резерв'], 'capture_field_codes' => ['current_provider', 'decision_criteria']],
                ['client_key' => 'price_probe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если вопрос в цене: «Понимаю. С чем сравниваем: конкретную ставку, бюджет или прошлую перевозку? Если нужно дешевле, важно понять, чем можно управлять: датой, машиной, документами, отсрочкой или объёмом». Заполните ориентир — {budget_window}.', 'hint' => 'Цена без условий не лечится скидкой. Сначала выясните базу сравнения.', 'sort_order' => 40, 'tags' => ['цена'], 'capture_field_codes' => ['budget_window']],
                ['client_key' => 'postpone_probe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если просят написать позже: «Окей, напишу. Чтобы письмо было полезным, подскажите, к чему вернуться: ставка, резерв, документы, новый маршрут или дата, когда тема оживёт?» Заполните дату — {next_step_date} и критерий — {decision_criteria}.', 'hint' => '“Напишите позже” превращайте в конкретный повод и дату.', 'sort_order' => 50, 'tags' => ['пауза'], 'capture_field_codes' => ['next_step_date', 'decision_criteria']],
                ['client_key' => 'return_to_source', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если возражение прояснили и клиент готов продолжить: «Спасибо, теперь понимаю контекст. Вернусь к основному вопросу и предложу следующий шаг уже с учётом того, что вы сказали».', 'hint' => 'После снятия возражения не начинайте заново. Вернитесь в исходный сценарий.', 'sort_order' => 60, 'tags' => ['возврат']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если возражение не снято: зафиксируйте причину, дату возможного возврата и исход. Не оставляйте “отказ” без критерия: цена, текущий подрядчик, нет потребности, не время или не ЛПР.', 'hint' => 'Даже отказ должен дать CRM понятную причину.', 'sort_order' => 70, 'tags' => ['завершение', 'CRM']],
            ],
            transitions: [
                ['from' => 'classify_objection', 'to' => 'no_need_probe', 'reaction' => 'no_need_objection', 'customer_label' => 'Не требуется / сейчас нет потребности'],
                ['from' => 'classify_objection', 'to' => 'competitor_probe', 'reaction' => 'competitor', 'customer_label' => 'У нас уже есть подрядчик'],
                ['from' => 'classify_objection', 'to' => 'price_probe', 'reaction' => 'price_objection', 'customer_label' => 'Вопрос только в цене'],
                ['from' => 'classify_objection', 'to' => 'postpone_probe', 'reaction' => 'stall', 'customer_label' => 'Напишите позже / сейчас не время'],
                ['from' => 'no_need_probe', 'to' => 'return_to_source', 'reaction' => 'positive_signal', 'customer_label' => 'Понял, можно продолжить разговор'],
                ['from' => 'competitor_probe', 'to' => 'return_to_source', 'reaction' => 'positive_signal', 'customer_label' => 'Резервный вариант можно обсудить'],
                ['from' => 'price_probe', 'to' => 'return_to_source', 'reaction' => 'positive_signal', 'customer_label' => 'Окей, сравним по условиям'],
                ['from' => 'postpone_probe', 'to' => 'return_to_source', 'reaction' => 'positive_signal', 'customer_label' => 'Да, зафиксировали повод и дату'],
                ['from' => 'no_need_probe', 'to' => 'end', 'reaction' => 'stall', 'customer_label' => 'Нет, тема точно не нужна'],
                ['from' => 'competitor_probe', 'to' => 'end', 'reaction' => 'stall', 'customer_label' => 'Нет, текущего подрядчика достаточно'],
                ['from' => 'price_probe', 'to' => 'end', 'reaction' => 'stall', 'customer_label' => 'Нет, по цене не подойдёте'],
                ['from' => 'postpone_probe', 'to' => 'end', 'reaction' => 'stall', 'customer_label' => 'Пока ничего фиксировать не надо'],
                ['from' => 'return_to_source', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Вернуться в основной сценарий', 'target_type' => 'return'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Реактивация тёплой базы',
            description: 'Обзвон контактов с прошлогодними касаниями: восстановить контекст, классифицировать статус и передать в дальнейшую проработку.',
            channel: 'phone',
            tags: ['реактивация', 'тёплая база', 'follow-up', 'ЛПР'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: восстановить прошлогодний контекст без давления и быстро понять, есть ли смысл в дальнейшей проработке. Сказать: «Добрый день. В прошлом году мы уже немного общались по логистике: либо обсуждали запросы, либо обменивались контактами или отправляли письмо. Сотрудник, который общался, уволился не оставил после себя информацию. Звоню восстановить контакт. Подскажите, как у вас сейчас дела с перевозками обстоят?» Заполните имя собеседника — {client_name}', 'hint' => 'Объясните причину звонка простым языком и сразу спросите про текущую ситуацию.', 'sort_order' => 10, 'tags' => ['старт', 'контекст'], 'capture_field_codes' => ['client_name']],
                ['client_key' => 'context_classify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: классифицировать прошлое касание и текущий статус. Сказать: «Поскольку информации у нас не осталось, мне необходимо задать вам несколько вопросов. Это займёт до 10 минут» Затем уточнить: кто сейчас отвечает за логистику — {decision_maker}, какие направления/объёмы актуальны — {routes}, {volume_forecast}, что поменялось за год — {decision_criteria}.', 'hint' => 'В этой базе разные уровни теплоты. Сначала восстановите факты: ЛПР, маршруты, объём и что изменилось.', 'sort_order' => 20, 'tags' => ['квалификация', 'статус базы'], 'capture_field_codes' => ['decision_maker', 'routes', 'volume_forecast', 'decision_criteria']],
                ['client_key' => 'lpr_route', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Если собеседник не ЛПР или контакт устарел: «Подскажите, а кто сейчас принимает решения по перевозкам и выбору подрядчиков? Как корректно обратиться, какие контакты?» Заполните ЛПР — {decision_maker}, почту/канал — {email} , дату повторного касания — {next_step_date}.', 'hint' => 'Нужен не дожим через не-ЛПР, а правильный контакт и разрешение вернуться.', 'sort_order' => 30, 'tags' => ['ЛПР', 'маршрут контакта'], 'capture_field_codes' => ['decision_maker', 'email', 'next_step_date']],
                ['client_key' => 'refresh_need', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если прошлый запрос был, но устарел: «Понимаю, прошлогодние вводные уже могли измениться. Чтобы я не гадал, расскажите мне, а я зафиксирую актуальные маршруты {routes}, объём {volume_forecast}, сроки и критерии выбора {decision_criteria}. После этого обсудим следующий шаг: обновлённый расчёт, подписание договора либо ещё какие-то варианты».', 'hint' => 'Не считайте по старым данным. Дайте клиенту спокойно обновить вводные.', 'sort_order' => 40, 'tags' => ['обновить вводные', 'запрос'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_criteria']],
                ['client_key' => 'value_probe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если контакт готов общаться: «Тогда коротко сверю, где можем быть полезны сейчас: резерв машин на пиковые периоды, ставка по регулярным маршрутам, контроль документов, сроки подачи или запасной подрядчик. Что из этого для вас реально может иметь значение в этом году?» Зафиксируйте критерии выбора — {decision_criteria} и текущего подрядчика/схему — {current_provider}.', 'hint' => 'Не презентуйте всё. Дайте меню полезности и попросите клиента выбрать актуальную боль.', 'sort_order' => 50, 'tags' => ['ценность', 'диагностика'], 'capture_field_codes' => ['decision_criteria', 'current_provider']],
                ['client_key' => 'soft_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если отвечают «не актуально / работаем с другими / пишите потом»: «Понимаю. Вряд ли вы ждали моего звонка полгода. Чтобы я зря не тратил ваше время, расскажите кратко: а что в текущем сотрудничестве вас устраивает? Что могло бы подтолкнуть вас к мысли сменить его?» Заполните причину/критерий — {decision_criteria}.', 'hint' => 'Не закрывайте возражение датой сразу. Сначала выясните, что устраивает и что могло бы стать причиной для смены.', 'sort_order' => 60, 'tags' => ['возражение', 'пауза'], 'capture_field_codes' => ['decision_criteria']],
                ['client_key' => 'competitor_or_partner', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если уже есть перевозчик/партнёр: «Хорошо, значит основной процесс закрыт. Мы можем быть не заменой, а резервом: один проблемный маршрут, пиковая нагрузка, срочная подача или сравнение условий без риска для текущей схемы. Где резерв был бы полезен, если основной подрядчик не справится?» Зафиксируйте текущего подрядчика — {current_provider}.', 'hint' => 'Не атакуйте действующего подрядчика. Предлагайте резерв и снижение риска.', 'sort_order' => 70, 'tags' => ['конкурент', 'резерв'], 'capture_field_codes' => ['current_provider']],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Закрыть разговор передачей в дальнейшую проработку: «Фиксирую: контакт/ЛПР — {decision_maker}, актуальность — {decision_criteria}, маршруты/объём — {routes} / {volume_forecast}, следующий шаг — письмо, КП, созвон или повторное касание на дату {next_step_date}. Я внесу это в CRM, чтобы следующий контакт был уже по делу».', 'hint' => 'Итог должен быть пригоден для CRM: статус базы, ЛПР, причина интереса/паузы и конкретный следующий шаг.', 'sort_order' => 80, 'tags' => ['следующий шаг', 'CRM'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После звонка: обновите карточку контрагента или лида. Обязательно сохраните: уровень теплоты контакта, ЛПР/канал, что было в прошлом касании, текущую актуальность, главный блокер и дату следующей задачи. Если контакт нецелевой — отметьте это, чтобы база не возвращалась в слепой обзвон.', 'hint' => 'Главная цель обзвона базы — не “поговорили”, а чистая сегментация и очередь следующих действий.', 'sort_order' => 90, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'context_classify', 'reaction' => null, 'customer_label' => 'Да, расскажите, что хотели уточнить'],
                ['from' => 'context_classify', 'to' => 'value_probe', 'reaction' => 'positive_signal', 'customer_label' => 'Да, тема логистики актуальна'],
                ['from' => 'context_classify', 'to' => 'refresh_need', 'reaction' => 'need_info', 'customer_label' => 'Старые данные уже неактуальны, нужно обновить вводные'],
                ['from' => 'context_classify', 'to' => 'lpr_route', 'reaction' => 'stall', 'customer_label' => 'Я не занимаюсь этим / напишите на почту'],
                ['from' => 'context_classify', 'to' => 'competitor_or_partner', 'reaction' => 'competitor', 'customer_label' => 'Сейчас работаем с другим перевозчиком'],
                ['from' => 'context_classify', 'to' => 'soft_objection', 'reaction' => 'no_need_objection', 'customer_label' => 'Не требуется / сейчас не актуально', 'target_type' => 'script', 'target_sales_script_version_id' => $objectionHandlingVersion->id],
                ['from' => 'context_classify', 'to' => 'soft_objection', 'reaction' => 'price_objection', 'customer_label' => 'Если будет дешевле, можно смотреть', 'target_type' => 'script', 'target_sales_script_version_id' => $objectionHandlingVersion->id],
                ['from' => 'value_probe', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Есть смысл обсудить следующий шаг'],
                ['from' => 'refresh_need', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Обновите предложение по новым вводным'],
                ['from' => 'lpr_route', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Контакт ЛПР и канал записали'],
                ['from' => 'competitor_or_partner', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Резервный вариант можно оставить'],
                ['from' => 'soft_objection', 'to' => 'next_step', 'reaction' => null, 'customer_label' => 'Вернитесь в согласованную дату'],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Да, следующий шаг зафиксировали'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Переговоры по цене и марже',
            description: 'Структурная работа с давлением на ставку: обмен уступок, альтернативные условия и защита маржи.',
            channel: 'phone',
            tags: ['цена', 'маржа', 'переговоры', 'ставка'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: не спорить о цене, а понять, с чем клиент сравнивает. Сказать: «Понимаю, ставка важна. Давайте разберём, что именно нужно снизить и чем можно управлять: дата подачи, машина, документы, отсрочка, объём или регулярность». Заполните целевую ставку клиента — {target_rate}.', 'hint' => 'Не обещайте скидку до понимания условий.', 'sort_order' => 10, 'tags' => ['старт', 'цена'], 'capture_field_codes' => ['target_rate']],
                ['client_key' => 'price_diagnosis', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: выяснить источник давления по цене. Спросить: «С чем сравниваем: конкретное КП, бюджет, прошлую перевозку или внутренний лимит?» Зафиксировать критерий — {decision_criteria}, конкурента — {current_provider}, бюджет — {budget_window}.', 'hint' => 'Разные причины цены требуют разных ответов.', 'sort_order' => 20, 'tags' => ['диагностика цены'], 'capture_field_codes' => ['decision_criteria', 'current_provider', 'budget_window']],
                ['client_key' => 'tradeoff_menu', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Показать варианты: «Могу проверить несколько путей: другая дата подачи, проще требования к документам, регулярный объём или текущая ставка, но с более сильными гарантиями. Что из этого для вас реально?»', 'hint' => 'Уступка должна быть обменом, а не просто скидкой.', 'sort_order' => 30, 'tags' => ['обмен уступок']],
                ['client_key' => 'competitor_compare', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если есть конкурент: «Давайте сравним по одинаковым условиям: ставка, простой, документы, отсрочка, резерв и ответственность при срыве подачи. Если по тем же условиям мы проигрываем — честно посмотрю, где можем усилиться».', 'hint' => 'Не ругайте конкурента. Сравнивайте условия.', 'sort_order' => 40, 'tags' => ['конкурент', 'сравнение']],
                ['client_key' => 'protect_margin', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Защитить маржу вопросом: «Если мы попадаем в ваш ориентир {target_rate}, что готовы зафиксировать взамен: объём, срок решения, предоплату, регулярность или упрощение требований?» Заполните условия оплаты — {payment_terms} и объём — {volume_forecast}.', 'hint' => 'Скидка без встречного условия быстро становится нормой.', 'sort_order' => 50, 'tags' => ['маржа', 'условия'], 'capture_field_codes' => ['payment_terms', 'volume_forecast']],
                ['client_key' => 'close_offer', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Закрыть рамку: «Фиксирую: целевая ставка — {target_rate}, условие для пересмотра — {payment_terms}/{volume_forecast}, критерий выбора — {decision_criteria}. Я вернусь с вариантом: базовая ставка, ставка с изменёнными условиями и пояснение рисков».', 'hint' => 'Отправляйте не одну цифру, а 2 варианта с понятным составом услуги.', 'sort_order' => 60, 'tags' => ['КП', 'следующий шаг']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После разговора: сохраните целевую ставку, встречные условия, критерий сравнения и дедлайн решения. Если дали уступку — обязательно зафиксируйте, что получили взамен.', 'hint' => 'Переговоры по цене должны оставлять след в CRM, иначе аналитика маржи не объяснит решение.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'price_diagnosis', 'reaction' => null, 'customer_label' => 'Да, давайте разберём, почему дорого'],
                ['from' => 'price_diagnosis', 'to' => 'tradeoff_menu', 'reaction' => 'positive_signal', 'customer_label' => 'Готовы смотреть варианты условий'],
                ['from' => 'price_diagnosis', 'to' => 'competitor_compare', 'reaction' => 'competitor', 'customer_label' => 'У конкурента дешевле'],
                ['from' => 'price_diagnosis', 'to' => 'protect_margin', 'reaction' => 'price_objection', 'customer_label' => 'Нам нужно попасть в конкретную ставку'],
                ['from' => 'price_diagnosis', 'to' => 'tradeoff_menu', 'reaction' => 'need_info', 'customer_label' => 'Нужно понять, за счёт чего можно снизить'],
                ['from' => 'price_diagnosis', 'to' => 'close_offer', 'reaction' => 'stall', 'customer_label' => 'Пока просто сравниваем рынок'],
                ['from' => 'tradeoff_menu', 'to' => 'protect_margin', 'reaction' => null, 'customer_label' => 'Один из вариантов можно обсудить'],
                ['from' => 'competitor_compare', 'to' => 'protect_margin', 'reaction' => null, 'customer_label' => 'Сравним по одинаковым условиям'],
                ['from' => 'protect_margin', 'to' => 'close_offer', 'reaction' => null, 'customer_label' => 'Встречные условия понятны'],
                ['from' => 'close_offer', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Жду два варианта ставки'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Проблемный рейс / удержание клиента',
            description: 'Разговор при срыве, задержке или претензии: признать риск, собрать факты, дать план восстановления и удержать доверие.',
            channel: 'phone',
            tags: ['удержание', 'претензия', 'рейс', 'кризис'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: снизить напряжение и взять ответственность за процесс. Сказать: «Понимаю, ситуация неприятная. Сейчас моя задача — не спорить, а быстро собрать факты, дать план действий и держать вас в курсе до решения». Заполните причину претензии — {claim_reason}.', 'hint' => 'В конфликте первым продаётся контроль. Не оправдывайтесь до фактов.', 'sort_order' => 10, 'tags' => ['старт', 'конфликт'], 'capture_field_codes' => ['claim_reason']],
                ['client_key' => 'fact_check', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: собрать факты и вернуть клиенту ощущение контроля. Спросить: что произошло, где груз/машина, какой ущерб или риск, кто ждёт информацию, какой дедлайн критичен. Заполнить план восстановления — {service_recovery_plan} и дедлайн — {decision_deadline}.', 'hint' => 'Сейчас нельзя спорить, обещать компенсацию или защищаться. Сначала факт, ответственный и время следующего апдейта.', 'sort_order' => 20, 'tags' => ['факты', 'SLA'], 'capture_field_codes' => ['service_recovery_plan', 'decision_deadline']],
                ['client_key' => 'acknowledge_delay', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если задержка подтверждена: «Да, задержка есть. План такой: проверяем ETA, резервный вариант, контакт ответственного, частота статусов. Первый апдейт — в конкретное время, затем каждые N минут/часов до решения».', 'hint' => 'Назовите следующий апдейт временем, а не «скоро».', 'sort_order' => 30, 'tags' => ['задержка', 'статусы']],
                ['client_key' => 'document_issue', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если проблема в документах: «Проверяем, где документ застрял: водитель, делопроизводство, контрагент, оригинал/скан. Я дам список: что есть, чего нет, кто ответственный и когда закрываем». Заполните требуемые документы — {required_documents}.', 'hint' => 'Документная проблема решается чек-листом, а не обещанием «разберёмся».', 'sort_order' => 40, 'tags' => ['документы'], 'capture_field_codes' => ['required_documents']],
                ['client_key' => 'compensation_frame', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если клиент требует компенсацию: «Я не буду сейчас называть цифру без проверки. Зафиксируем факт, последствия и документы. После внутренней сверки вернусь с корректным вариантом: что признаём, что исправляем, какие меры, чтобы не повторилось».', 'hint' => 'Не спорьте о компенсации в эмоциях. Сначала факты и регламент претензии.', 'sort_order' => 50, 'tags' => ['претензия', 'компенсация']],
                ['client_key' => 'restore_trust', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Восстановить доверие: «После решения я пришлю короткий разбор: причина, что сделали, как меняем контроль на следующих рейсах. Если вы готовы, следующий рейс ведём с усиленным регламентом статусов и одним ответственным». Заполните следующий шаг — {next_step_date}.', 'hint' => 'Удержание — это не извинение, а доказуемое изменение процесса.', 'sort_order' => 60, 'tags' => ['удержание', 'следующий шаг'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После звонка: внесите заметку в заказ, причину претензии, план восстановления, время следующего апдейта и ответственного. Если нужен руководитель — создайте задачу с дедлайном и ссылкой на заказ.', 'hint' => 'Кризисный звонок без записи в CRM повышает риск повторного конфликта.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'fact_check', 'reaction' => null, 'customer_label' => 'Хорошо, объясните что происходит'],
                ['from' => 'fact_check', 'to' => 'acknowledge_delay', 'reaction' => 'positive_signal', 'customer_label' => 'Жду конкретный план по задержке'],
                ['from' => 'fact_check', 'to' => 'document_issue', 'reaction' => 'need_info', 'customer_label' => 'Проблема с документами / не хватает бумаг'],
                ['from' => 'fact_check', 'to' => 'compensation_frame', 'reaction' => 'price_objection', 'customer_label' => 'Кто компенсирует простой/штраф?'],
                ['from' => 'fact_check', 'to' => 'compensation_frame', 'reaction' => 'stall', 'customer_label' => 'Мы недовольны и пока ставим всё на паузу'],
                ['from' => 'fact_check', 'to' => 'restore_trust', 'reaction' => 'competitor', 'customer_label' => 'После такого уйдём к другому подрядчику'],
                ['from' => 'acknowledge_delay', 'to' => 'restore_trust', 'reaction' => null, 'customer_label' => 'Жду апдейты по регламенту'],
                ['from' => 'document_issue', 'to' => 'restore_trust', 'reaction' => null, 'customer_label' => 'Жду список и сроки по документам'],
                ['from' => 'compensation_frame', 'to' => 'restore_trust', 'reaction' => null, 'customer_label' => 'Хорошо, фиксируйте претензию официально'],
                ['from' => 'restore_trust', 'to' => 'end', 'reaction' => null, 'customer_label' => 'План восстановления понятен'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Повторная продажа действующему клиенту',
            description: 'Расширение работы с текущим клиентом: новый маршрут, регулярный объём, собственный парк или сервисный пакет.',
            channel: 'meeting',
            tags: ['повторная продажа', 'апсейл', 'свой парк', 'регулярные рейсы'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Цель шага: перейти от “мы уже работаем” к конкретному расширению. Сказать: «Хочу обсудить не абстрактно больше объёма, а где мы можем снять с вас нагрузку: новые маршруты, пиковые периоды, резерв, свой парк, документы или регулярный график».', 'hint' => 'Действующему клиенту не нужна презентация с нуля. Нужна гипотеза расширения.', 'sort_order' => 10, 'tags' => ['старт', 'апсейл']],
                ['client_key' => 'growth_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: найти одну точку расширения. Спросить: какие направления растут — {routes}; прогноз объёма — {volume_forecast}; где текущая схема не справляется; кто принимает решение — {decision_maker}; какие критерии успеха — {decision_criteria}.', 'hint' => 'Не просите “дайте нам всё”. Хороший ответ клиента указывает на маршрут, пик, риск, собственный парк или регулярный объём.', 'sort_order' => 20, 'tags' => ['диагностика роста'], 'capture_field_codes' => ['routes', 'volume_forecast', 'decision_maker', 'decision_criteria']],
                ['client_key' => 'own_fleet_pitch', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если важен контроль: «На часть рейсов можем предложить собственный парк: понятный водитель/ТС, быстрый статус, меньше зависимость от рынка, прозрачный контакт ответственного. Это не замена всем подрядчикам, а инструмент для критичных рейсов». Заполните аргумент своего парка — {own_fleet_argument}.', 'hint' => 'Собственный парк продаётся не “у нас есть машины”, а контроль и предсказуемость на критичных рейсах.', 'sort_order' => 30, 'tags' => ['свой парк', 'контроль'], 'capture_field_codes' => ['own_fleet_argument']],
                ['client_key' => 'regularity_offer', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если есть регулярный объём: «Давайте зафиксируем регулярную схему: прогноз объёма, окна подачи, SLA по статусам, график документов и условия оплаты. Тогда мы можем лучше планировать ресурсы и держать качество стабильнее». Заполните условия оплаты — {payment_terms}.', 'hint' => 'Регулярность должна давать обеим сторонам предсказуемость: объём ↔ условия ↔ SLA.', 'sort_order' => 40, 'tags' => ['регулярность', 'условия'], 'capture_field_codes' => ['payment_terms']],
                ['client_key' => 'budget_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если давят бюджетом: «Согласен, расширение должно быть экономически оправдано. Давайте посчитаем: стоимость рейса, простои, ручной контроль, документы, срывы, резерв. Если наша схема дороже, покажем, какой риск или труд она снимает». Заполните бюджетный ориентир — {budget_window}.', 'hint' => 'Апсейл действующему клиенту должен показывать экономику процесса, не только цену рейса.', 'sort_order' => 50, 'tags' => ['бюджет', 'экономика'], 'capture_field_codes' => ['budget_window']],
                ['client_key' => 'pilot_close', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Закрыть пилотом: «Предлагаю 30 дней на одном направлении: объём {volume_forecast}, критерии {decision_criteria}, условия {payment_terms}, ответственный с вашей стороны {decision_maker}, дата ревью {next_step_date}. После факта решаем, расширять или нет».', 'hint' => 'Пилот для действующего клиента должен иметь ревью и критерии, иначе он растворяется в текущей работе.', 'sort_order' => 60, 'tags' => ['пилот', 'ревью'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После разговора: создайте задачу на пилот/КП/допсоглашение, обновите портрет клиента: где растёт объём, кто ЛПР, что важно для расширения, какой аргумент сработал.', 'hint' => 'Повторная продажа должна обновлять портрет клиента, а не только создавать КП.', 'sort_order' => 70, 'tags' => ['CRM', 'завершение']],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'growth_probe', 'reaction' => null, 'customer_label' => 'Да, можно обсудить расширение'],
                ['from' => 'growth_probe', 'to' => 'regularity_offer', 'reaction' => 'positive_signal', 'customer_label' => 'Объём растёт, нужна стабильная схема'],
                ['from' => 'growth_probe', 'to' => 'budget_objection', 'reaction' => 'price_objection', 'customer_label' => 'Расширение возможно только при понятной экономике'],
                ['from' => 'growth_probe', 'to' => 'own_fleet_pitch', 'reaction' => 'need_info', 'customer_label' => 'Нам важен контроль на критичных рейсах'],
                ['from' => 'growth_probe', 'to' => 'pilot_close', 'reaction' => 'stall', 'customer_label' => 'Пока не готовы расширять всё сразу'],
                ['from' => 'growth_probe', 'to' => 'own_fleet_pitch', 'reaction' => 'competitor', 'customer_label' => 'Часть объёма уже закрывает другой подрядчик'],
                ['from' => 'own_fleet_pitch', 'to' => 'pilot_close', 'reaction' => null, 'customer_label' => 'Критичные рейсы можно попробовать через свой парк'],
                ['from' => 'regularity_offer', 'to' => 'pilot_close', 'reaction' => null, 'customer_label' => 'Регулярную схему можно протестировать'],
                ['from' => 'budget_objection', 'to' => 'pilot_close', 'reaction' => null, 'customer_label' => 'Считайте экономику пилота'],
                ['from' => 'pilot_close', 'to' => 'end', 'reaction' => null, 'customer_label' => 'Пилот и дата ревью согласованы'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Тренажёр: цена и конкурент',
            description: 'Интенсив для отработки давления по ставке, сравнения с конкурентом и обмена уступок без потери маржи.',
            channel: 'phone',
            tags: ['тренажёр', 'цена', 'конкурент', 'маржа'],
            entryNodeKey: 'trainer_intro',
            nodes: [
                ['client_key' => 'trainer_intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка: клиент сразу давит на цену и сравнивает с другим перевозчиком. Откройте разговор спокойно: признайте цену, попросите сравнивать одинаковые условия и задайте один диагностический вопрос. Заполните целевую ставку — {target_rate}.', 'hint' => 'Оценка: менеджер не оправдывается, не обещает скидку, а переводит разговор в критерии сравнения.', 'sort_order' => 10, 'tags' => ['тренажёр', 'старт'], 'capture_field_codes' => ['target_rate']],
                ['client_key' => 'trainer_branch', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход тренировки: выяснить, почему клиент давит на цену. Спросить: «С чем сравниваете и что готовы зафиксировать взамен лучшей ставки?» Собрать критерии — {decision_criteria}, конкурента — {current_provider}, объём — {volume_forecast}.', 'hint' => 'Менеджер не должен видеть меню реакций. Он должен услышать: конкурент, бюджет, скидка без условий, объём или внутреннее согласование.', 'sort_order' => 20, 'tags' => ['ветвление', 'тренажёр'], 'capture_field_codes' => ['decision_criteria', 'current_provider', 'volume_forecast']],
                ['client_key' => 'trainer_competitor', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отработка конкурента: «Подскажите, по чему именно нас сравниваете: ставка, отсрочка, документы, срок подачи, ответственность за срыв? Если условия одинаковые — я честно посмотрю, где можем усилиться».', 'hint' => 'Запрещено ругать конкурента. Нужно вытащить критерий проигрыша.', 'sort_order' => 30, 'tags' => ['конкурент', 'тренажёр']],
                ['client_key' => 'trainer_tradeoff', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отработка обмена: «Если попадаем в {target_rate}, что можем закрепить взамен: объём {volume_forecast}, предоплату/срок оплаты {payment_terms}, регулярность или упрощение требований?»', 'hint' => 'Хороший ответ связывает скидку с встречным обязательством.', 'sort_order' => 40, 'tags' => ['обмен', 'маржа'], 'capture_field_codes' => ['payment_terms']],
                ['client_key' => 'trainer_close', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Завершить: «Я подготовлю два варианта: базовый и с изменёнными условиями. В каждом укажу, что входит, какие риски остаются и до какого времени ставка актуальна». Заполните дату следующего шага — {next_step_date}.', 'hint' => 'Финал тренировки — 2 варианта КП и дедлайн, а не «подумаем».', 'sort_order' => 50, 'tags' => ['итог', 'КП'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'trainer_end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После тренировки: оцените, была ли уступка обменом, спросили ли критерий сравнения, зафиксировали ли целевую ставку и следующий шаг.', 'hint' => 'Разбор должен назвать одну сильную сторону и одну ошибку по марже.', 'sort_order' => 60, 'tags' => ['разбор']],
            ],
            transitions: [
                ['from' => 'trainer_intro', 'to' => 'trainer_branch', 'reaction' => null, 'customer_label' => 'У вас дороже, чем у другого перевозчика'],
                ['from' => 'trainer_branch', 'to' => 'trainer_competitor', 'reaction' => 'competitor', 'customer_label' => 'Есть конкретное КП дешевле'],
                ['from' => 'trainer_branch', 'to' => 'trainer_tradeoff', 'reaction' => 'price_objection', 'customer_label' => 'Нужна ставка ниже без лишних разговоров'],
                ['from' => 'trainer_branch', 'to' => 'trainer_tradeoff', 'reaction' => 'positive_signal', 'customer_label' => 'Объём готовы обсуждать'],
                ['from' => 'trainer_branch', 'to' => 'trainer_close', 'reaction' => 'need_info', 'customer_label' => 'Нужно согласовать условия внутри'],
                ['from' => 'trainer_branch', 'to' => 'trainer_close', 'reaction' => 'stall', 'customer_label' => 'Пока просто сравниваем рынок'],
                ['from' => 'trainer_competitor', 'to' => 'trainer_close', 'reaction' => null, 'customer_label' => 'Сравните по одинаковым условиям'],
                ['from' => 'trainer_tradeoff', 'to' => 'trainer_close', 'reaction' => null, 'customer_label' => 'Встречные условия можно обсудить'],
                ['from' => 'trainer_close', 'to' => 'trainer_end', 'reaction' => null, 'customer_label' => 'Жду два варианта КП'],
            ],
            reactionIds: $reactionIds,
        );

        $this->seedScript(
            title: 'Тренажёр: конфликт и удержание',
            description: 'Интенсив для отработки претензий: задержка, документы, компенсация, план восстановления и сохранение клиента.',
            channel: 'phone',
            tags: ['тренажёр', 'конфликт', 'удержание', 'документы'],
            entryNodeKey: 'trainer_intro',
            nodes: [
                ['client_key' => 'trainer_intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Тренировка: клиент раздражён из-за рейса. Начните с признания ситуации и рамки контроля: «Сейчас моя задача — собрать факты, дать план и держать вас в курсе до решения». Заполните причину претензии — {claim_reason}.', 'hint' => 'Ошибка: спорить или оправдываться до сбора фактов.', 'sort_order' => 10, 'tags' => ['тренажёр', 'конфликт'], 'capture_field_codes' => ['claim_reason']],
                ['client_key' => 'trainer_branch', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход тренировки: уточнить, что именно болит в претензии. Спросить: «Проблема в сроке, документах, простое/штрафе или риске повторения?» Заполнить план восстановления — {service_recovery_plan} и нужные документы — {required_documents}.', 'hint' => 'В конфликте менеджер должен услышать тип риска, а не выбирать ветку глазами. Каждая ветка должна закончиться временем следующего апдейта.', 'sort_order' => 20, 'tags' => ['ветвление', 'претензия'], 'capture_field_codes' => ['service_recovery_plan', 'required_documents']],
                ['client_key' => 'trainer_delay', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отработка задержки: «Задержка есть. Я проверяю фактическое местоположение, ETA, резерв и контакт ответственного. Первый апдейт дам в конкретное время, дальше — по регламенту до закрытия».', 'hint' => 'Называйте время апдейта и ответственного.', 'sort_order' => 30, 'tags' => ['задержка']],
                ['client_key' => 'trainer_docs', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отработка документов: «Сейчас разбираю, какого документа не хватает, у кого он находится и когда будет скан/оригинал. Пришлю чек-лист: есть / нет / ответственный / срок».', 'hint' => 'Документы требуют чек-листа и дедлайна.', 'sort_order' => 40, 'tags' => ['документы']],
                ['client_key' => 'trainer_compensation', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отработка компенсации: «Фиксирую претензию. Чтобы не обещать на эмоциях, сверяем факт, последствия и документы. После проверки вернусь с официальным вариантом: что признаём, как исправляем, как предотвращаем повтор».', 'hint' => 'Не называйте компенсацию без фактов и регламента.', 'sort_order' => 50, 'tags' => ['компенсация']],
                ['client_key' => 'trainer_trust', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Восстановление доверия: «После решения пришлю короткий разбор причины и новый регламент контроля для следующих рейсов. На ближайший рейс назначим одного ответственного и усиленный статусный режим». Заполните следующий шаг — {next_step_date}.', 'hint' => 'Удержание должно показывать изменение процесса, не только извинение.', 'sort_order' => 60, 'tags' => ['удержание'], 'capture_field_codes' => ['next_step_date']],
                ['client_key' => 'trainer_end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'После тренировки: оцените, признали ли проблему, собрали ли факты, дали ли план восстановления и конкретный следующий апдейт.', 'hint' => 'Разбор конфликта должен быть по процессу: факт, план, время, ответственный.', 'sort_order' => 70, 'tags' => ['разбор']],
            ],
            transitions: [
                ['from' => 'trainer_intro', 'to' => 'trainer_branch', 'reaction' => null, 'customer_label' => 'Объясняйте, что вы будете делать'],
                ['from' => 'trainer_branch', 'to' => 'trainer_delay', 'reaction' => 'positive_signal', 'customer_label' => 'Жду план по задержке'],
                ['from' => 'trainer_branch', 'to' => 'trainer_docs', 'reaction' => 'need_info', 'customer_label' => 'У нас проблема с документами'],
                ['from' => 'trainer_branch', 'to' => 'trainer_compensation', 'reaction' => 'price_objection', 'customer_label' => 'Кто компенсирует простой?'],
                ['from' => 'trainer_branch', 'to' => 'trainer_trust', 'reaction' => 'competitor', 'customer_label' => 'После такого уйдём к другому'],
                ['from' => 'trainer_branch', 'to' => 'trainer_trust', 'reaction' => 'stall', 'customer_label' => 'Ставим будущие заказы на паузу'],
                ['from' => 'trainer_delay', 'to' => 'trainer_trust', 'reaction' => null, 'customer_label' => 'Жду апдейт в названное время'],
                ['from' => 'trainer_docs', 'to' => 'trainer_trust', 'reaction' => null, 'customer_label' => 'Жду чек-лист по документам'],
                ['from' => 'trainer_compensation', 'to' => 'trainer_trust', 'reaction' => null, 'customer_label' => 'Оформляйте претензию и план'],
                ['from' => 'trainer_trust', 'to' => 'trainer_end', 'reaction' => null, 'customer_label' => 'План понятен'],
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
                ['client_key' => 'status_check', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: выяснить, что случилось с КП. Спросить: «КП посмотрели? Кто принимает решение? С чем сравнивают? Что должно быть в предложении, чтобы его согласовали?» Зафиксировать критерии — {decision_criteria}, бюджет/ставку — {budget_window}, канал — {email}.', 'hint' => 'Не спрашивайте “ну что решили?”. Ищите конкретный блокер: цена, конкурент, нет ЛПР, нет срочности или не хватает данных.', 'sort_order' => 20, 'tags' => ['статус КП', 'ветвление'], 'capture_field_codes' => ['decision_criteria', 'budget_window', 'email']],
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
                ['client_key' => 'requirements_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Текущий ход: собрать матрицу требований до обсуждения ставки. Спросить: маршруты и объёмы — {routes}, {volume_forecast}; обязательные документы; отсрочка/форма оплаты — {payment_terms}; дедлайн подачи — {decision_deadline}; критерии кроме цены — {decision_criteria}.', 'hint' => 'Закупщик оценивает соответствие регламенту. Пока нет матрицы критериев, цена и презентация будут шумом.', 'sort_order' => 20, 'tags' => ['требования', 'тендер'], 'capture_field_codes' => ['routes', 'volume_forecast', 'payment_terms', 'decision_deadline', 'decision_criteria']],
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
            ['key' => 'no_need_objection', 'label' => 'Нет потребности / не требуется', 'sort_order' => 60],
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
            ['code' => 'target_rate', 'label' => 'Целевая ставка клиента', 'sort_order' => 150],
            ['code' => 'decision_maker', 'label' => 'ЛПР / согласующий', 'sort_order' => 160],
            ['code' => 'required_documents', 'label' => 'Требуемые документы', 'sort_order' => 170],
            ['code' => 'claim_reason', 'label' => 'Причина претензии', 'sort_order' => 180],
            ['code' => 'service_recovery_plan', 'label' => 'План восстановления сервиса', 'sort_order' => 190],
            ['code' => 'own_fleet_argument', 'label' => 'Аргумент собственного парка', 'sort_order' => 200],
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
     * @param  list<array{from:string,to:string,reaction:?string,customer_label?:?string,target_type?:string,target_sales_script_version_id?:int|null}>  $transitions
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
    ): SalesScriptVersion {
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

        foreach ($transitions as $index => $transitionPayload) {
            $targetType = (string) ($transitionPayload['target_type'] ?? 'node');
            SalesScriptTransition::query()->create([
                'sales_script_version_id' => $version->id,
                'from_node_id' => $nodeIds[$transitionPayload['from']],
                'to_node_id' => $nodeIds[$transitionPayload['to']],
                'target_type' => $targetType,
                'target_sales_script_version_id' => $targetType === 'script'
                    ? ($transitionPayload['target_sales_script_version_id'] ?? null)
                    : null,
                'sales_script_reaction_class_id' => $transitionPayload['reaction'] !== null
                    ? $reactionIds[$transitionPayload['reaction']]
                    : null,
                'customer_label' => $transitionPayload['customer_label'] ?? null,
                'sort_order' => $index,
            ]);
        }

        return $version;
    }
}
