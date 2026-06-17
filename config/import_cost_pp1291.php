<?php

/**
 * Утилизационный сбор: ПП РФ № 1291 (коммерческий ввоз юрлицами).
 * fee_rub = round(base_fee_rub × coefficient) по возрастной группе.
 *
 * Коэффициенты ориентировочные для продажной калькуляции; обновляйте при новой редакции ПП.
 */
return [

    'decree_reference' => 'ПП РФ № 1291',
    'decree_effective_from' => '2026-02-06',
    'payer_note' => 'Юридическое лицо, коммерческое использование',

    'categories' => [
        'bulldozer' => [
            'name' => 'Бульдозеры',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 15.92],
                ['max_age_years' => 5, 'coefficient' => 23.87],
                ['max_age_years' => 7, 'coefficient' => 34.18],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 40.96],
            ],
        ],
        'crawler_excavator' => [
            'name' => 'Экскаваторы гусеничные',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 15.91],
                ['max_age_years' => 5, 'coefficient' => 23.87],
                ['max_age_years' => 7, 'coefficient' => 34.18],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 40.96],
            ],
        ],
        'wheeled_loader' => [
            'name' => 'Погрузчики фронтальные / колёсные',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 11.99],
                ['max_age_years' => 5, 'coefficient' => 17.99],
                ['max_age_years' => 7, 'coefficient' => 25.71],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 30.80],
            ],
        ],
        'motor_grader' => [
            'name' => 'Грейдеры самоходные',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 11.03],
                ['max_age_years' => 5, 'coefficient' => 16.54],
                ['max_age_years' => 7, 'coefficient' => 23.65],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 28.34],
            ],
        ],
        'road_tractor' => [
            'name' => 'Тягачи седельные',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 10.16],
                ['max_age_years' => 5, 'coefficient' => 15.23],
                ['max_age_years' => 7, 'coefficient' => 21.78],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 26.10],
            ],
        ],
        'truck' => [
            'name' => 'Грузовые автомобили',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 12.50],
                ['max_age_years' => 5, 'coefficient' => 18.74],
                ['max_age_years' => 7, 'coefficient' => 26.79],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 32.12],
            ],
        ],
        'works_truck' => [
            'name' => 'Автопогрузчики / works trucks',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 6.58],
                ['max_age_years' => 5, 'coefficient' => 9.86],
                ['max_age_years' => 7, 'coefficient' => 14.10],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 16.91],
            ],
        ],
        'self_propelled_other' => [
            'name' => 'Прочая самоходная техника',
            'base_fee_rub' => 150_000,
            'age_coefficients' => [
                ['max_age_years' => 3, 'coefficient' => 8.00],
                ['max_age_years' => 5, 'coefficient' => 12.00],
                ['max_age_years' => 7, 'coefficient' => 16.00],
                ['max_age_years' => PHP_INT_MAX, 'coefficient' => 19.33],
            ],
        ],
    ],
];
