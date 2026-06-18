<?php

return [

    /*
  |--------------------------------------------------------------------------
  | Ориентировочные ставки для калькулятора растаможки
  |--------------------------------------------------------------------------
  |
  | Справочник не заменяет консультацию таможенного представителя.
  | Ставки и утильсбор обновляйте по актуальным ПП РФ и ТН ВЭД ЕАЭС.
  |
  */

    'default_vat_percent' => 22,

    'disclaimer' => 'Ориентировочный расчёт для продажной цены: ставки пошлины — из Alta API (при наличии ключа), иначе kodtnved.ru, утильсбор — по ПП РФ № 1291. Не заменяет таможенную декларацию.',

    'eec' => [
        'base_url' => 'https://portal.eaeunion.org/sites/odata/_api',
        'metadata_list' => 'Список метаданных',
        'registry_title_keywords' => ['ТН ВЭД', 'ЕТТ', 'тариф', 'пошлин', 'ТНВЭД'],
        'timeout_seconds' => 45,
        'page_size' => 200,
        'code_prefixes' => ['8429', '8430', '8701', '8704', '8709'],
    ],

    'alta' => [
        'base_url' => 'https://www.alta.ru/tnved/xml/',
        'login' => env('IMPORT_COST_ALTA_LOGIN'),
        'password' => env('IMPORT_COST_ALTA_PASSWORD'),
        'default_country_code' => env('IMPORT_COST_ALTA_COUNTRY_CODE', '156'),
        'timeout_seconds' => 30,
        'delay_ms' => 500,
        'batch_limit' => 200,
    ],

    'kodtnved' => [
        'base_url' => 'https://kodtnved.ru',
        'timeout_seconds' => 30,
        'delay_ms' => 1000,
        'batch_limit' => 200,
    ],

    /**
     * Сопоставление префикса кода ТН ВЭД с категорией утильсбора (ПП № 1291).
     *
     * @var list<array{prefix: string, category: string}>
     */
    'pp1291_prefix_map' => [
        ['prefix' => '842911', 'category' => 'bulldozer'],
        ['prefix' => '842919', 'category' => 'bulldozer'],
        ['prefix' => '842920', 'category' => 'motor_grader'],
        ['prefix' => '842940', 'category' => 'self_propelled_other'],
        ['prefix' => '842951', 'category' => 'wheeled_loader'],
        ['prefix' => '842952', 'category' => 'crawler_excavator'],
        ['prefix' => '842959', 'category' => 'crawler_excavator'],
        ['prefix' => '843041', 'category' => 'self_propelled_other'],
        ['prefix' => '843049', 'category' => 'self_propelled_other'],
        ['prefix' => '843061', 'category' => 'crawler_excavator'],
        ['prefix' => '870120', 'category' => 'road_tractor'],
        ['prefix' => '870421', 'category' => 'truck'],
        ['prefix' => '870422', 'category' => 'truck'],
        ['prefix' => '870423', 'category' => 'truck'],
        ['prefix' => '870911', 'category' => 'works_truck'],
        ['prefix' => '870919', 'category' => 'works_truck'],
    ],

    'currencies' => [
        ['code' => 'RUB', 'label' => '₽ RUB'],
        ['code' => 'USD', 'label' => '$ USD'],
        ['code' => 'EUR', 'label' => '€ EUR'],
        ['code' => 'CNY', 'label' => '¥ CNY'],
    ],

    /**
     * Таможенный сбор за оформление (шкала по таможенной стоимости, руб.).
     *
     * @var list<array{max: float, fee: int}>
     */
    'customs_processing_fee_tiers' => [
        ['max' => 200_000, 'fee' => 775],
        ['max' => 450_000, 'fee' => 1_550],
        ['max' => 1_200_000, 'fee' => 3_100],
        ['max' => 2_500_000, 'fee' => 8_530],
        ['max' => 5_000_000, 'fee' => 12_000],
        ['max' => 10_000_000, 'fee' => 23_000],
        ['max' => PHP_FLOAT_MAX, 'fee' => 30_000],
    ],

    /**
     * Утильсбор для юрлиц (коммерческий ввоз самоходной техники).
     * fee_rub — ориентир по возрастной группе (полных лет с года выпуска).
     *
     * @var array<string, array{label: string, fees_by_age: list<array{max_age_years: int, fee_rub: int}>}>
     */
    'utilization_profiles' => [
        'crawler_excavator' => [
            'label' => 'Экскаваторы гусеничные',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 2_387_200],
                ['max_age_years' => 5, 'fee_rub' => 3_580_800],
                ['max_age_years' => 7, 'fee_rub' => 5_126_400],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 6_144_000],
            ],
        ],
        'wheeled_loader' => [
            'label' => 'Погрузчики фронтальные / колёсные',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 1_798_800],
                ['max_age_years' => 5, 'fee_rub' => 2_698_200],
                ['max_age_years' => 7, 'fee_rub' => 3_856_800],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 4_620_000],
            ],
        ],
        'bulldozer' => [
            'label' => 'Бульдозеры',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 2_156_400],
                ['max_age_years' => 5, 'fee_rub' => 3_234_600],
                ['max_age_years' => 7, 'fee_rub' => 4_620_000],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 5_544_000],
            ],
        ],
        'motor_grader' => [
            'label' => 'Грейдеры самоходные',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 1_654_200],
                ['max_age_years' => 5, 'fee_rub' => 2_481_300],
                ['max_age_years' => 7, 'fee_rub' => 3_547_800],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 4_250_400],
            ],
        ],
        'road_tractor' => [
            'label' => 'Тягачи седельные',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 1_523_400],
                ['max_age_years' => 5, 'fee_rub' => 2_285_100],
                ['max_age_years' => 7, 'fee_rub' => 3_267_600],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 3_915_600],
            ],
        ],
        'truck' => [
            'label' => 'Грузовые автомобили',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 1_874_400],
                ['max_age_years' => 5, 'fee_rub' => 2_811_600],
                ['max_age_years' => 7, 'fee_rub' => 4_018_800],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 4_818_000],
            ],
        ],
        'works_truck' => [
            'label' => 'Автопогрузчики / works trucks',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 986_400],
                ['max_age_years' => 5, 'fee_rub' => 1_479_600],
                ['max_age_years' => 7, 'fee_rub' => 2_115_600],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 2_536_800],
            ],
        ],
        'self_propelled_other' => [
            'label' => 'Прочая самоходная техника',
            'fees_by_age' => [
                ['max_age_years' => 3, 'fee_rub' => 1_200_000],
                ['max_age_years' => 5, 'fee_rub' => 1_800_000],
                ['max_age_years' => 7, 'fee_rub' => 2_400_000],
                ['max_age_years' => PHP_INT_MAX, 'fee_rub' => 2_900_000],
            ],
        ],
    ],

    /**
     * Коды ТН ВЭД (самоходная техника и смежные ТС).
     *
     * @var list<array{
     *     code: string,
     *     code_display: string,
     *     label: string,
     *     duty_percent: float,
     *     vat_percent: float|null,
     *     utilization_profile: string|null,
     *     requires_utilization_fee: bool
     * }>
     */
    'tn_ved_codes' => [
        [
            'code' => '8429110000',
            'code_display' => '8429.11',
            'label' => 'Бульдозеры гусеничные',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'bulldozer',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8429190000',
            'code_display' => '8429.19',
            'label' => 'Бульдозеры прочие',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'bulldozer',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8429200000',
            'code_display' => '8429.20',
            'label' => 'Грейдеры и планировщики',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'motor_grader',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8429400000',
            'code_display' => '8429.40',
            'label' => 'Катки для уплотнения',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'self_propelled_other',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8429510000',
            'code_display' => '8429.51',
            'label' => 'Погрузчики фронтальные на гусеницах',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'wheeled_loader',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8429520000',
            'code_display' => '8429.52',
            'label' => 'Машины полноповоротные (укрупнённый код)',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'crawler_excavator',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8429590000',
            'code_display' => '8429.59',
            'label' => 'Экскаваторы прочие самоходные',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'crawler_excavator',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8430410000',
            'code_display' => '8430.41',
            'label' => 'Скреперы самоходные',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'self_propelled_other',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8430490000',
            'code_display' => '8430.49',
            'label' => 'Скреперы прочие',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'self_propelled_other',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8430610000',
            'code_display' => '8430.61',
            'label' => 'Копатели траншейные',
            'duty_percent' => 0,
            'vat_percent' => null,
            'utilization_profile' => 'crawler_excavator',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8701200000',
            'code_display' => '8701.20',
            'label' => 'Тягачи седельные для полуприцепов',
            'duty_percent' => 15,
            'vat_percent' => null,
            'utilization_profile' => 'road_tractor',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8704210000',
            'code_display' => '8704.21',
            'label' => 'Грузовики с дизелем, полная масса ≤ 5 т',
            'duty_percent' => 15,
            'vat_percent' => null,
            'utilization_profile' => 'truck',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8704220000',
            'code_display' => '8704.22',
            'label' => 'Грузовики с дизелем, 5–20 т',
            'duty_percent' => 15,
            'vat_percent' => null,
            'utilization_profile' => 'truck',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8704230000',
            'code_display' => '8704.23',
            'label' => 'Грузовики с дизелем, > 20 т',
            'duty_percent' => 15,
            'vat_percent' => null,
            'utilization_profile' => 'truck',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8709110000',
            'code_display' => '8709.11',
            'label' => 'Электрические works trucks',
            'duty_percent' => 5,
            'vat_percent' => null,
            'utilization_profile' => 'works_truck',
            'requires_utilization_fee' => true,
        ],
        [
            'code' => '8709190000',
            'code_display' => '8709.19',
            'label' => 'Works trucks прочие',
            'duty_percent' => 5,
            'vat_percent' => null,
            'utilization_profile' => 'works_truck',
            'requires_utilization_fee' => true,
        ],
    ],
];
