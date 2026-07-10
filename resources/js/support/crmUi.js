/**
 * Единые визуальные классы для CRM: кнопки «создать», «разрушающее», вторичная.
 * Стили задаются в resources/css/crm-appearance.css и crm-workspace-skin.css.
 */

export const crmBtnCreate = 'crm-btn-create';

export const crmBtnDangerMuted = 'crm-btn-danger-muted';

export const crmBtnNeutral = 'crm-btn-neutral';

/** Вторичная ссылка рядом с «Создать» (например «Перейти в канбан») */
export const crmBtnSecondaryOutline = 'crm-btn-secondary-outline';

/** Заголовок страницы модуля */
export const crmPageTitle = 'crm-page-title';

export const crmPageLead = 'crm-page-lead';

export const crmPageEyebrow = 'crm-page-eyebrow';

/** Заголовок секции внутри панели (график, блок отчёта) */
export const crmSectionTitle = 'crm-section-title';

/** Панель / карточка секции */
export const crmPanel = 'crm-panel';

export const crmStatCard = 'crm-stat-card';

export const crmStatCardRose = 'crm-stat-card crm-stat-card--rose';

export const crmModuleCard = 'crm-module-card group';

export const crmMobileTile = 'crm-mobile-tile';

/** Компактное поле (даты, фильтры); для форм на всю ширину — crmFieldFluid */
export const crmField = 'crm-field';

export const crmFieldWide = 'crm-field crm-field--wide';

export const crmFieldFluid = 'crm-field crm-field--fluid';

/** Компактное поле «дней» в блоке условий оплаты контрагента */
export const crmFieldDays = 'crm-field crm-field--days';

/** Узкий select «Оплата» в блоке условий оплаты контрагента */
export const crmFieldPaymentMode = 'crm-field crm-field--payment-mode';

/** Компактные поля строки транша (мастер заказа → Финансы) */
export const crmFieldPaymentInstallment = 'crm-field crm-field--payment-installment';

/** Read-only блок с рамкой как у поля ввода */
export const crmFieldDisplay = 'crm-field-display';

/** Компактное поле в строке нормативов/штрафов */
export const crmFieldCompact = 'crm-field crm-field--compact';

export const crmLabel = 'crm-label';

export const crmLabelCompact = 'crm-label crm-label--compact';

export const crmFilterBar = 'crm-filter-bar';

export const crmFilterField = 'crm-filter-field';

export const crmPageTitleSm = 'crm-page-title crm-page-title--sm';

export const crmBtnPrimary = 'crm-btn-primary';

/** Кнопки выбора реакции в прохождении скрипта (мягче, чем sky-700) */
export const crmBtnScriptChoice = 'crm-btn-script-choice';

export const crmBtnSecondary = 'crm-btn-secondary';

export const crmPill = 'crm-pill';

export const crmPillActive = 'crm-pill crm-pill--active';

export const crmSegmented = 'crm-segmented';

export const crmSegmentedBtn = 'crm-segmented-btn';

export const crmSegmentedBtnActive = 'crm-segmented-btn crm-segmented-btn--active';

/** Элемент списка в боковой панели настроек */
export const crmListItem = 'crm-list-item';

export const crmListItemIdle = 'crm-list-item crm-list-item--idle';

export const crmListItemActive = 'crm-list-item crm-list-item--active';

export const crmListItemActiveSoft = 'crm-list-item crm-list-item--active-soft';

/** @deprecated используйте crmBtnPrimary */
export const crmBtnPrimaryCompact = 'crm-btn-primary';

/** Оболочка мастера в модалке (ТС, водитель, заказ) */
export const crmWizardShell = 'crm-wizard-shell';

export const crmWizardHeader = 'crm-wizard-header';

export const crmWizardBack = 'crm-wizard-back';

export const crmWizardBody = 'crm-wizard-body';

/** Контейнер AG Grid на странице реестра */
export const crmGridPanel = 'crm-grid-panel flex min-h-0 flex-1 flex-col overflow-hidden p-1';

/** Внутренняя оболочка AG Grid (без дублирующей рамки — её даёт crmGridPanel) */
export const crmGridInnerPanel = 'flex min-h-0 flex-1 flex-col overflow-hidden';

/** Поиск в тулбаре грида */
export const crmGridSearchField = 'crm-field crm-grid-search-field';

/** Поиск в тулбаре грида (шире — документы) */
export const crmGridSearchFieldWide = 'crm-field crm-grid-search-field crm-grid-search-field--wide';

/** Кнопка тулбара грида (колонки, сброс, плотность) */
export const crmGridToolbarBtn = 'crm-btn-neutral inline-flex items-center gap-2 px-2.5 py-1.5 text-sm';

/** Выпадающее меню тулбара грида */
export const crmGridDropdown = 'crm-panel absolute left-0 top-full z-20 mt-2 w-40 p-1.5 shadow-xl';

/** Панель модального окна (подключается к <Modal>) */
export const crmModalPanel = 'crm-modal-panel';

/** Мастер в модалке: лиды, контрагенты, автопарк (7xl, высокий) */
export const crmModalEntityShell = 'crm-modal-entity-shell';

/** Форма в модалке: пользователи, документы (5xl, скролл тела) */
export const crmModalFormShell = 'crm-modal-form-shell';

export const crmModalFormBody = 'crm-modal-form-body';

/** Компактная строка поля в модалке: подпись слева, control справа */
export const crmModalFieldRow = 'crm-modal-field-row';

/** Подпись inline-поля модалки */
export const crmModalFieldLabel = 'crm-modal-field-label';

/** Обёртка flex-wrap для нескольких inline-полей */
export const crmModalFieldsWrap = 'crm-modal-fields-wrap';

/** Поле с подписью сверху (textarea, списки чекбоксов) */
export const crmModalFieldStack = 'crm-modal-field-stack';

/** Чекбокс в формах CRM */
export const crmCheckbox = 'crm-checkbox';
