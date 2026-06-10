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
        $reactionIds = $this->seedReactionClasses();

        $this->seedScript(
            title: 'Первичный запрос ставки (экспедиция)',
            description: 'Пилотный сценарий: приветствие, квалификация и типовые ветки.',
            channel: 'phone',
            tags: ['экспедиция', 'ставка'],
            entryNodeKey: 'intro',
            nodes: [
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Добрый день! Компания [название], меня зовут [имя]. Вы запрашивали расчёт по перевозке — удобно пару минут уточнить параметры?', 'hint' => 'Говорите спокойно и зафиксируйте контактное лицо.', 'sort_order' => 10],
                ['client_key' => 'qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Уточните маршрут, срок готовности груза и что именно везём (вес, объём, особые условия). После ответа клиента выберите тип реакции ниже.', 'hint' => 'Не озвучивайте ставку до минимальной квалификации.', 'sort_order' => 20],
                ['client_key' => 'price_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Понимаю, бюджет важен. Наша ставка учитывает маршрут, срок и ответственность за сопровождение. Давайте сверим, что входит в расчёт — так проще сравнить с альтернативами.', 'hint' => 'Переводите разговор в ценность: срок, страхование, мониторинг.', 'sort_order' => 30],
                ['client_key' => 'need_info', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Зафиксирую недостающие данные и вернусь с уточняющими вопросами или черновой ставкой в течение [X] часов.', 'hint' => 'Назовите реалистичный SLA.', 'sort_order' => 40],
                ['client_key' => 'positive', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отлично, данных достаточно для просчёта. Отправлю КП на почту или в мессенджер до [время]. Удобно?', 'hint' => 'Подтвердите канал и ФИО получателя.', 'sort_order' => 50],
                ['client_key' => 'wrapup', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Кратко подытожу: маршрут [..], срок [..], я готовлю ставку. Если появятся изменения по грузу — сразу напишите, скорректируем.', 'hint' => 'Попросите подтверждение от клиента.', 'sort_order' => 60],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Сценарий завершён. Зафиксируйте итог разговора и главное возражение.', 'hint' => null, 'sort_order' => 70],
            ],
            transitions: [
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
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Добрый день! [Имя], компания [название]. Работаем с логистикой и перевозками в B2B. Подскажите, пожалуйста, кто у вас курирует перевозки и выбор подрядчиков?', 'hint' => 'Тон уважительный, темп спокойный, не давите.', 'sort_order' => 10],
                ['client_key' => 'gatekeeper_branch', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'Если вышли на ЛПР, переходите к блоку знакомства. Если не вышли — запросите корректный контакт и время повторного касания.', 'hint' => 'СПИН-S: сначала ситуация, потом детали.', 'sort_order' => 20],
                ['client_key' => 'clarify_contact', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Подскажите, как правильно обратиться к ЛПР: ФИО, должность, удобный канал связи и время для короткого звонка?', 'hint' => 'Фрейм НЛП: «чтобы не отвлекать лишний раз».', 'sort_order' => 30],
                ['client_key' => 'lpr_open', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Рад(а) знакомству. Чтобы сразу быть полезным(ой), задам 3 коротких вопроса по процессу перевозок — так пойму, есть ли смысл продолжать.', 'hint' => 'Принцип согласия на микро-диалог.', 'sort_order' => 40],
                ['client_key' => 'spin_probe', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'СПИН: 1) Как сейчас организованы перевозки? 2) Где чаще всего возникают сбои/задержки? 3) Во что это упирается по срокам, деньгам, нервам команды? 4) Если это улучшить в ближайший квартал — какой эффект будет для вас?', 'hint' => 'S→P→I→N в одной логике разговора.', 'sort_order' => 50],
                ['client_key' => 'value_pitch', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Судя по вашим вводным, можем закрыть это через прозрачный SLA, контроль статусов и резерв по перевозчикам. Предлагаю короткий 20-минутный разбор кейса и пилотный маршрут.', 'hint' => 'Говорите выгодой ЛПР, а не «о нас».', 'sort_order' => 60],
                ['client_key' => 'soft_objection', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Понимаю скепсис. Давайте без обязательств: на одном маршруте покажем цифры по сроку/стоимости/рискам, затем вместе решите, продолжать или нет.', 'hint' => 'Снижение риска входа + контроль у клиента.', 'sort_order' => 70],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Фиксируем следующий шаг: дата и время созвона, список данных для расчёта, участники. Отправляю краткую повестку и подтверждение в течение 10 минут.', 'hint' => 'Всегда фиксируйте конкретику в календаре.', 'sort_order' => 80],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Сценарий завершён. Отметьте исход: вышли/не вышли на ЛПР, какой триггер сработал, что мешало.', 'hint' => null, 'sort_order' => 90],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'gatekeeper_branch', 'reaction' => null],
                ['from' => 'gatekeeper_branch', 'to' => 'lpr_open', 'reaction' => 'positive_signal', 'customer_label' => 'Да, это я / соединяю с ЛПР'],
                ['from' => 'gatekeeper_branch', 'to' => 'clarify_contact', 'reaction' => 'need_info', 'customer_label' => 'Скажите, что вы хотели?'],
                ['from' => 'gatekeeper_branch', 'to' => 'clarify_contact', 'reaction' => 'stall', 'customer_label' => 'Не сейчас, напишите на почту'],
                ['from' => 'clarify_contact', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'lpr_open', 'to' => 'spin_probe', 'reaction' => null],
                ['from' => 'spin_probe', 'to' => 'value_pitch', 'reaction' => 'positive_signal'],
                ['from' => 'spin_probe', 'to' => 'soft_objection', 'reaction' => 'price_objection'],
                ['from' => 'spin_probe', 'to' => 'soft_objection', 'reaction' => 'competitor'],
                ['from' => 'spin_probe', 'to' => 'next_step', 'reaction' => 'need_info'],
                ['from' => 'spin_probe', 'to' => 'next_step', 'reaction' => 'stall'],
                ['from' => 'value_pitch', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'soft_objection', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null],
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
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Спасибо, что нашли время. Цель созвона — понять ваш текущий процесс и критерии выбора перевозчиков, чтобы предложить решение без лишних обещаний.', 'hint' => 'Сразу задайте рамку и время встречи.', 'sort_order' => 10],
                ['client_key' => 'spin_discovery', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'СПИН-блок: S — как сейчас устроен процесс выбора и запуска перевозки? P — где чаще всего «узкие места»? I — как это влияет на сроки, бюджет, репутацию? N — что для вас будет ощутимым улучшением через 1-2 месяца?', 'hint' => 'Слушайте больше, говорите меньше.', 'sort_order' => 20],
                ['client_key' => 'criteria_probe', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Какие критерии для нового перевозчика обязательны: документы, страхование, география, SLA, IT-интеграция, отсрочка, тарифы?', 'hint' => 'Фиксируйте «must-have» отдельно от «nice-to-have».', 'sort_order' => 30],
                ['client_key' => 'procedure_probe', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Как выглядит процедура входа нового перевозчика: этапы, согласующие лица, сроки, тестовый период?', 'hint' => 'Выявляйте реальный путь принятия решения.', 'sort_order' => 40],
                ['client_key' => 'anti_criteria', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'Что вы точно не хотите видеть у нового перевозчика? Какие ошибки были критичными в прошлом?', 'hint' => 'Вопрос на избегание рисков повышает доверие.', 'sort_order' => 50],
                ['client_key' => 'proposal_frame', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Предлагаю следующий формат: показываем пилот на 1-2 маршрутах, измеряем KPI (срок, точность, отклонения, коммуникация), после чего вместе решаем масштабирование.', 'hint' => 'НЛП-фрейм «проверяем на фактах».', 'sort_order' => 60],
                ['client_key' => 'objection_handle', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если сейчас рано принимать решение, это нормально. Тогда зафиксируем минимальный пакет требований и вернёмся с точечным предложением под вашу процедуру.', 'hint' => 'Снимайте давление, оставляйте контроль клиенту.', 'sort_order' => 70],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Итог: зафиксированы критерии, процедура и точки риска. Следующий шаг — отправляю резюме встречи и дорожную карту входа с датами.', 'hint' => 'Подтвердите ответственного и дедлайн.', 'sort_order' => 80],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Сценарий завершён. Сохраните ключевые критерии выбора и блокеры.', 'hint' => null, 'sort_order' => 90],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'spin_discovery', 'reaction' => null],
                ['from' => 'spin_discovery', 'to' => 'criteria_probe', 'reaction' => 'positive_signal', 'customer_label' => 'Да, задавайте вопросы'],
                ['from' => 'spin_discovery', 'to' => 'procedure_probe', 'reaction' => 'need_info', 'customer_label' => 'Нужно уточнить у коллег'],
                ['from' => 'spin_discovery', 'to' => 'objection_handle', 'reaction' => 'stall', 'customer_label' => 'Сейчас не готов общаться, пишите на почту'],
                ['from' => 'spin_discovery', 'to' => 'objection_handle', 'reaction' => 'competitor', 'customer_label' => 'У нас уже есть перевозчик'],
                ['from' => 'spin_discovery', 'to' => 'objection_handle', 'reaction' => 'price_objection', 'customer_label' => 'Сначала скажите цену'],
                ['from' => 'criteria_probe', 'to' => 'procedure_probe', 'reaction' => null],
                ['from' => 'procedure_probe', 'to' => 'anti_criteria', 'reaction' => null],
                ['from' => 'anti_criteria', 'to' => 'proposal_frame', 'reaction' => null],
                ['from' => 'proposal_frame', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'objection_handle', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null],
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
                ['client_key' => 'intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Спасибо за совместную работу. Хочу обсудить, как увеличить полезный объём и качество сервиса без роста операционного стресса для вашей команды.', 'hint' => 'Начинайте с признания текущего сотрудничества.', 'sort_order' => 10],
                ['client_key' => 'spin_growth', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'СПИН-блок по росту: S — какие направления/маршруты сейчас в приоритете? P — где теряются деньги или время в текущей схеме? I — во что это выливается по бюджету и планированию? N — какой формат сотрудничества даст вам лучший результат в квартале?', 'hint' => 'Ведите к измеримому эффекту.', 'sort_order' => 20],
                ['client_key' => 'growth_offer', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Предлагаю масштабирование через пакет: закреплённые слоты, SLA по срокам, приоритетный диспетчер и прозрачные KPI. Это даст предсказуемость и защиту от срывов в пике.', 'hint' => 'Язык выгоды ЛПР: предсказуемость, контроль, снижение рисков.', 'sort_order' => 30],
                ['client_key' => 'roi_reframe', 'kind' => SalesScriptNodeKind::Say, 'body' => 'По цене: если смотреть только ставку, картина неполная. Предлагаю считать совокупную экономику — простои, штрафы, скорость оборота и стоимость координации.', 'hint' => 'Рамка TCO вместо «дешевле/дороже».', 'sort_order' => 40],
                ['client_key' => 'terms_negotiation', 'kind' => SalesScriptNodeKind::Ask, 'body' => 'По условиям оплаты: какой формат будет для вас рабочим при увеличении объёма? Можем согласовать компромисс через KPI и предсказуемый график.', 'hint' => 'НЛП-подстройка: «взаимовыгодный баланс».', 'sort_order' => 50],
                ['client_key' => 'pilot_frame', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Если нужно снизить риск, запускаем пилот на расширенном объёме на 30 дней с понятными метриками и контрольной точкой.', 'hint' => 'Пилот снимает барьер крупных изменений.', 'sort_order' => 60],
                ['client_key' => 'next_step', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Фиксируем шаги: объём пилота, финансовые условия, KPI, ответственные и дата ревью-колла.', 'hint' => 'Закрепите договорённости письменно сразу после звонка.', 'sort_order' => 70],
                ['client_key' => 'end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Сценарий завершён. Отметьте, какие аргументы сработали лучше для роста бюджета.', 'hint' => null, 'sort_order' => 80],
            ],
            transitions: [
                ['from' => 'intro', 'to' => 'spin_growth', 'reaction' => null],
                ['from' => 'spin_growth', 'to' => 'growth_offer', 'reaction' => 'positive_signal'],
                ['from' => 'spin_growth', 'to' => 'roi_reframe', 'reaction' => 'price_objection'],
                ['from' => 'spin_growth', 'to' => 'terms_negotiation', 'reaction' => 'need_info'],
                ['from' => 'spin_growth', 'to' => 'pilot_frame', 'reaction' => 'stall'],
                ['from' => 'spin_growth', 'to' => 'pilot_frame', 'reaction' => 'competitor'],
                ['from' => 'growth_offer', 'to' => 'terms_negotiation', 'reaction' => null],
                ['from' => 'roi_reframe', 'to' => 'terms_negotiation', 'reaction' => null],
                ['from' => 'terms_negotiation', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'pilot_frame', 'to' => 'next_step', 'reaction' => null],
                ['from' => 'next_step', 'to' => 'end', 'reaction' => null],
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
                ['client_key' => 'trainer_intro', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Добрый день! Компания [название], [имя]. Вы просили расчёт перевозки по маршруту — удобно за пару минут уточнить объём, срок готовности груза и ставку ориентировочно?', 'hint' => 'Зафиксируйте маршрут и срок; не обещайте точную ставку без данных.', 'sort_order' => 10],
                ['client_key' => 'trainer_qualify', 'kind' => SalesScriptNodeKind::Branch, 'body' => 'После ответа клиента выберите реакцию: позитив, возражение по цене, нужны документы/детали, откладывает или сравнивает с конкурентом.', 'hint' => 'Слушайте: упоминания конкурента, страховка, КП, SLA, штрафы за простой.', 'sort_order' => 20],
                ['client_key' => 'trainer_price', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Понимаю, ставка важна. Давайте сверим, что входит в расчёт: срок подачи транспорта, страхование груза, мониторинг и ответственность за срыв — иначе сравнение с конкурентом будет некорректным.', 'hint' => 'Переводите «дорого» в состав услуги и риски.', 'sort_order' => 30],
                ['client_key' => 'trainer_positive', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Отлично, по вводным можем считать ставку. Зафиксирую контакт для КП и отправлю расчёт с условиями страхования и SLA по статусам.', 'hint' => 'Назовите канал и срок, когда ждать КП.', 'sort_order' => 40],
                ['client_key' => 'trainer_need_docs', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Запрошу у вас пакет документов: карточку груза, адреса погрузки/выгрузки, требования к транспорту. После получения вернусь с уточняющими вопросами или черновой ставкой.', 'hint' => 'Чётко перечислите, какие документы критичны для расчёта.', 'sort_order' => 50],
                ['client_key' => 'trainer_wrap', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Кратко зафиксирую: маршрут, срок, ставка или следующий шаг по документам. Если что-то изменится по грузу — напишите, пересчитаем.', 'hint' => 'Попросите подтверждение у клиента.', 'sort_order' => 60],
                ['client_key' => 'trainer_end', 'kind' => SalesScriptNodeKind::Say, 'body' => 'Сценарий завершён. В тренажёре отметьте оценку диалога и при необходимости исход воронки.', 'hint' => null, 'sort_order' => 70],
            ],
            transitions: [
                ['from' => 'trainer_intro', 'to' => 'trainer_qualify', 'reaction' => null],
                ['from' => 'trainer_qualify', 'to' => 'trainer_price', 'reaction' => 'price_objection'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_positive', 'reaction' => 'positive_signal'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_need_docs', 'reaction' => 'need_info'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_price', 'reaction' => 'stall'],
                ['from' => 'trainer_qualify', 'to' => 'trainer_price', 'reaction' => 'competitor'],
                ['from' => 'trainer_price', 'to' => 'trainer_wrap', 'reaction' => null],
                ['from' => 'trainer_positive', 'to' => 'trainer_wrap', 'reaction' => null],
                ['from' => 'trainer_need_docs', 'to' => 'trainer_wrap', 'reaction' => null],
                ['from' => 'trainer_wrap', 'to' => 'trainer_end', 'reaction' => null],
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

    /**
     * @param  list<array{client_key:string,kind:SalesScriptNodeKind,body:string,hint:?string,sort_order:int,canvas_x?:int,canvas_y?:int}>  $nodes
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
