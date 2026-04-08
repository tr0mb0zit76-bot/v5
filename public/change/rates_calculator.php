<?php
// modules/rates_calculator.php

// Проверяем роль пользователя
if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    echo '<div class="alert alert-danger">У вас нет прав доступа к калькулятору</div>';

    return;
}

// Конфигурация модуля
$allowed_extensions = ['xlsx', 'xls'];
$upload_dir = __DIR__.'/../uploads/tariffs/';
$transport_types = [
    'sea' => 'Морские перевозки',
    'rail' => 'ЖД перевозки',
    'truck' => 'Автоперевозки',
    'multimodal' => 'Мультимодальные',
];

// Параметры для разных видов перевозок
$transport_params = [
    'sea' => [
        'containers' => [
            '20' => '20\'DC',
            '40' => '40\'HC',
        ],
        'transshipment_port' => 'Владивосток',
        'additional_costs' => [
            'terminal_handling' => 'Терминальная обработка',
            'port_dues' => 'Портовые сборы',
            'customs_clearance' => 'Таможенное оформление',
        ],
    ],
    'rail' => [
        'containers' => [
            '20_24' => '20\'DC (≤24т)',
            '20_28' => '20\'DC (>24т, ≤28т)',
            '40' => '40\'HC (≤28т)',
        ],
        'security' => 'Охрана груза',
    ],
    'truck' => [
        'truck_types' => [
            '13m_tilt' => '13.6м тентованный',
            '13m_box' => '13.6м бортовой',
            '20m' => '20м реф',
        ],
        'max_weight' => 22, // тонн
    ],
];

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin') {
    if (isset($_FILES['tariff_file']) && isset($_POST['transport_type'])) {
        $transport_type = $_POST['transport_type'];
        $file = $_FILES['tariff_file'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($extension, $allowed_extensions)) {
                $filename = $transport_type.'_tariff_'.date('Ymd_His').'.'.$extension;
                $filepath = $upload_dir.$filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Парсим и сохраняем в JSON
                    parseTariffFile($filepath, $transport_type);

                    log_action('tariff_upload', [
                        'user_id' => $user['id'],
                        'transport_type' => $transport_type,
                        'filename' => $filename,
                    ]);

                    $_SESSION['success_message'] = 'Тарифный файл успешно загружен и обработан';
                    header('Location: ?module=rates_calculator');
                    exit;
                }
            }
        }
    }
}

// Функция для парсинга Excel файла
function parseTariffFile($filepath, $transport_type)
{
    // В реальной реализации здесь будет парсинг PhpSpreadsheet
    // Для примера возвращаем структурированные данные

    $json_file = $upload_dir.$transport_type.'_latest.json';

    $sample_data = [
        'sea' => [
            'Владивосток' => [
                'ports' => [
                    'Читтагонг / Chittagong' => ['20' => 2750, '40' => 3500],
                    'Da Nang / Дананг' => ['20' => 2050, '40' => 3100],
                    'Hong Kong / Гонконг' => ['20' => 2250, '40' => 3250],
                    'Nhava Sheva / Нава Шева' => ['20' => 2750, '40' => 4000],
                ],
                'additional' => [
                    'terminal_handling' => 500,
                    'port_dues' => 300,
                    'customs_clearance' => 200,
                ],
            ],
        ],
        'rail' => [
            'routes' => [
                'Силикатная / Белый Раст/ Чехов (Москва)' => [
                    '20_24' => 185000,
                    '20_28' => 214130,
                    '40' => 300000,
                    'security_20' => 6041,
                    'security_40' => 10772,
                ],
                'Новосибирск-Восточный (Новосибирск)' => [
                    '20_24' => 125000,
                    '20_28' => 148020,
                    '40' => 210000,
                    'security_20' => 4384,
                    'security_40' => 7583,
                ],
            ],
        ],
        'truck' => [
            'border_points' => [
                'Alashankou' => [
                    'loading_fee_pallet' => 1500,
                    'loading_fee_box' => 3000,
                    'exchange_rate' => 7.15,
                    'routes' => [
                        'Guangzhou' => [
                            'china_freight' => 24500,
                            'destinations' => [
                                'Novosibirsk' => 5200,
                                'Moscow' => 6500,
                                'S.Petersburg' => 7000,
                            ],
                        ],
                    ],
                ],
                'Manzhouli' => [
                    'loading_fee_pallet' => 1200,
                    'loading_fee_box' => 2000,
                    'exchange_rate' => 7.15,
                    'routes' => [
                        'Guangzhou' => [
                            'china_freight' => 22500,
                            'destinations' => [
                                'Novosibirsk' => 9500,
                                'Moscow' => 11800,
                                'S.Petersburg' => 12300,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    file_put_contents($json_file, json_encode($sample_data[$transport_type] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $sample_data[$transport_type] ?? null;
}

// Функция для загрузки тарифов из JSON
function loadTariffs($transport_type)
{
    global $upload_dir;

    $json_file = $upload_dir.$transport_type.'_latest.json';
    if (file_exists($json_file)) {
        return json_decode(file_get_contents($json_file), true);
    }

    return null;
}

// Функция для расчета морской перевозки
function calculateSeaCost($data)
{
    $tariffs = loadTariffs('sea');
    if (! $tariffs) {
        return ['error' => 'Тарифы для морских перевозок не загружены'];
    }

    $port_from = $data['port_from'] ?? '';
    $container_type = $data['container_type'] ?? '20';
    $additional = $data['additional'] ?? [];

    // Основной фрахт
    if (! isset($tariffs['Владивосток']['ports'][$port_from][$container_type])) {
        return ['error' => 'Тариф для порта '.$port_from.' не найден'];
    }

    $base_cost = $tariffs['Владивосток']['ports'][$port_from][$container_type];
    $total = $base_cost;
    $breakdown = [
        ['name' => 'Морской фрахт', 'amount' => $base_cost, 'currency' => 'USD'],
    ];

    // Дополнительные расходы
    if (isset($tariffs['Владивосток']['additional'])) {
        foreach ($tariffs['Владивосток']['additional'] as $key => $amount) {
            if (in_array($key, $additional)) {
                $total += $amount;
                $breakdown[] = [
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'amount' => $amount,
                    'currency' => 'USD',
                ];
            }
        }
    }

    // Перевозка от/до порта Владивосток
    $vladivostok_transport = $data['vladivostok_transport'] ?? 0;
    if ($vladivostok_transport > 0) {
        $total += $vladivostok_transport;
        $breakdown[] = [
            'name' => 'Перевозка до/из Владивостока',
            'amount' => $vladivostok_transport,
            'currency' => 'RUB',
        ];
    }

    return [
        'total_usd' => round($total, 2),
        'total_rub' => round($total * ($data['exchange_rate'] ?? 75), 2),
        'breakdown' => $breakdown,
        'currency' => 'USD',
        'exchange_rate' => $data['exchange_rate'] ?? 75,
    ];
}

// Функция для расчета ЖД перевозки
function calculateRailCost($data)
{
    $tariffs = loadTariffs('rail');
    if (! $tariffs) {
        return ['error' => 'Тарифы для ЖД перевозок не загружены'];
    }

    $station_to = $data['station_to'] ?? '';
    $container_type = $data['container_type'] ?? '20_24';
    $include_security = $data['security'] ?? false;
    $weight = floatval($data['weight'] ?? 0);

    if (! isset($tariffs['routes'][$station_to])) {
        return ['error' => 'Тариф для станции '.$station_to.' не найден'];
    }

    $route_tariff = $tariffs['routes'][$station_to];

    // Определяем тип контейнера по весу
    if ($container_type === '20' && $weight > 24) {
        $container_type = '20_28';
    }

    $base_cost = $route_tariff[$container_type] ?? 0;
    $total = $base_cost;
    $breakdown = [
        ['name' => 'ЖД перевозка', 'amount' => $base_cost, 'currency' => 'RUB'],
    ];

    // Охрана груза
    if ($include_security) {
        $security_key = ($container_type === '40') ? 'security_40' : 'security_20';
        $security_cost = $route_tariff[$security_key] ?? 0;
        $total += $security_cost;
        $breakdown[] = [
            'name' => 'Охрана груза',
            'amount' => $security_cost,
            'currency' => 'RUB',
        ];
    }

    // Дополнительная перевозка от/до станции
    $station_transport = $data['station_transport'] ?? 0;
    if ($station_transport > 0) {
        $total += $station_transport;
        $breakdown[] = [
            'name' => 'Доставка до/от станции',
            'amount' => $station_transport,
            'currency' => 'RUB',
        ];
    }

    return [
        'total_rub' => round($total, 2),
        'breakdown' => $breakdown,
        'currency' => 'RUB',
    ];
}

// Функция для расчета авто перевозки
function calculateTruckCost($data)
{
    $tariffs = loadTariffs('truck');
    if (! $tariffs) {
        return ['error' => 'Тарифы для авто перевозок не загружены'];
    }

    $border_point = $data['border_point'] ?? 'Alashankou';
    $origin_city = $data['origin_city'] ?? '';
    $destination = $data['destination'] ?? '';
    $loading_type = $data['loading_type'] ?? 'box';
    $truck_type = $data['truck_type'] ?? '13m_tilt';

    if (! isset($tariffs['border_points'][$border_point])) {
        return ['error' => 'Пограничный пункт не найден'];
    }

    $border = $tariffs['border_points'][$border_point];

    if (! isset($border['routes'][$origin_city])) {
        return ['error' => 'Маршрут из '.$origin_city.' не найден'];
    }

    $route = $border['routes'][$origin_city];
    $destination_cost = $route['destinations'][$destination] ?? 0;

    if ($destination_cost === 0) {
        return ['error' => 'Направление в '.$destination.' не найдено'];
    }

    // Расчет по формуле из Excel
    $loading_fee = ($loading_type === 'box') ? $border['loading_fee_box'] : $border['loading_fee_pallet'];
    $exchange_rate = $border['exchange_rate'];

    // Формула: (China Freight + Loading Fee) / Exchange Rate + Destination Cost
    $total_usd = ($route['china_freight'] + $loading_fee) / $exchange_rate + $destination_cost;

    $breakdown = [
        ['name' => 'Китайский участок', 'amount' => $route['china_freight'], 'currency' => 'CNY'],
        ['name' => 'Погрузка на границе', 'amount' => $loading_fee, 'currency' => 'CNY'],
        ['name' => 'Российский участок', 'amount' => $destination_cost, 'currency' => 'USD'],
    ];

    return [
        'total_usd' => round($total_usd, 2),
        'total_rub' => round($total_usd * ($data['exchange_rate'] ?? 75), 2),
        'breakdown' => $breakdown,
        'currency' => 'USD',
        'exchange_rate' => $exchange_rate,
    ];
}

// Функция для расчета мультимодальной перевозки
function calculateMultimodalCost($data)
{
    $components = $data['components'] ?? [];
    $total = 0;
    $breakdown = [];
    $errors = [];

    foreach ($components as $component) {
        $type = $component['type'];
        $component_data = $component['data'];

        switch ($type) {
            case 'sea':
                $result = calculateSeaCost($component_data);
                break;
            case 'rail':
                $result = calculateRailCost($component_data);
                break;
            case 'truck':
                $result = calculateTruckCost($component_data);
                break;
            default:
                $errors[] = "Неизвестный тип перевозки: $type";

                continue 2;
        }

        if (isset($result['error'])) {
            $errors[] = $result['error'];

            continue;
        }

        // Конвертация в рубли
        if (isset($result['total_rub'])) {
            $component_total = $result['total_rub'];
        } elseif (isset($result['total_usd'])) {
            $component_total = $result['total_usd'] * ($component_data['exchange_rate'] ?? 75);
        } else {
            continue;
        }

        $total += $component_total;
        $breakdown[] = [
            'type' => $transport_types[$type] ?? $type,
            'total' => round($component_total, 2),
            'details' => $result['breakdown'] ?? [],
        ];
    }

    if (! empty($errors)) {
        return ['error' => implode(', ', $errors)];
    }

    return [
        'total_rub' => round($total, 2),
        'breakdown' => $breakdown,
        'components_count' => count($components),
    ];
}

// Обработка запроса на расчет
if (isset($_GET['calculate'])) {
    $calculation_type = $_GET['calculation_type'] ?? 'sea';

    switch ($calculation_type) {
        case 'sea':
            $result = calculateSeaCost([
                'port_from' => $_GET['port_from'] ?? '',
                'container_type' => $_GET['container_type'] ?? '20',
                'additional' => $_GET['additional'] ?? [],
                'vladivostok_transport' => floatval($_GET['vladivostok_transport'] ?? 0),
                'exchange_rate' => floatval($_GET['exchange_rate'] ?? 75),
            ]);
            break;

        case 'rail':
            $result = calculateRailCost([
                'station_to' => $_GET['station_to'] ?? '',
                'container_type' => $_GET['container_type'] ?? '20_24',
                'weight' => floatval($_GET['weight'] ?? 0),
                'security' => isset($_GET['security']),
                'station_transport' => floatval($_GET['station_transport'] ?? 0),
            ]);
            break;

        case 'truck':
            $result = calculateTruckCost([
                'border_point' => $_GET['border_point'] ?? 'Alashankou',
                'origin_city' => $_GET['origin_city'] ?? '',
                'destination' => $_GET['destination'] ?? '',
                'loading_type' => $_GET['loading_type'] ?? 'box',
                'truck_type' => $_GET['truck_type'] ?? '13m_tilt',
                'exchange_rate' => floatval($_GET['exchange_rate'] ?? 75),
            ]);
            break;

        case 'multimodal':
            // Здесь будет сложная логика сборки компонентов
            $result = ['error' => 'Мультимодальный расчет в разработке'];
            break;

        default:
            $result = ['error' => 'Неизвестный тип расчета'];
    }
}
?>

<div class="content-header">
    <h1>Калькулятор ставок</h1>
    <p>Расчет стоимости перевозок по тарифам партнеров</p>
</div>

<?php if (isset($_SESSION['success_message'])) { ?>
    <div class="alert alert-success">
        <?= $_SESSION['success_message'] ?>
        <?php unset($_SESSION['success_message']); ?>
    </div>
<?php } ?>

<!-- Вкладки для выбора типа перевозки -->
<div style="margin-bottom: 30px;">
    <div class="tabs" style="display: flex; border-bottom: 2px solid var(--border-color);">
        <?php foreach ($transport_types as $key => $name) { ?>
            <button class="tab-btn <?= ($_GET['calculation_type'] ?? 'sea') === $key ? 'active' : '' ?>"
                    onclick="window.location.href='?module=rates_calculator&calculation_type=<?= $key ?>'"
                    style="padding: 15px 25px; background: none; border: none; color: var(--text-secondary); 
                           cursor: pointer; font-weight: 600; border-bottom: 3px solid transparent;
                           transition: var(--transition);">
                <?= $name ?>
            </button>
        <?php } ?>
    </div>
</div>

<div class="cards-grid">
    <!-- Форма калькулятора -->
    <div class="card form-container">
        <h3 style="margin-bottom: 25px;">Расчет стоимости - <?= $transport_types[$_GET['calculation_type'] ?? 'sea'] ?></h3>
        
        <?php if (($_GET['calculation_type'] ?? 'sea') === 'sea') { ?>
        <!-- Форма для морских перевозок -->
        <form method="GET" action="?module=rates_calculator">
            <input type="hidden" name="calculate" value="1">
            <input type="hidden" name="calculation_type" value="sea">
            
            <div class="form-group">
                <label for="port_from">Порт отправления *</label>
                <select class="form-control" id="port_from" name="port_from" required>
                    <option value="">Выберите порт</option>
                    <option value="Читтагонг / Chittagong">Читтагонг / Chittagong</option>
                    <option value="Da Nang / Дананг">Da Nang / Дананг</option>
                    <option value="Hong Kong / Гонконг">Hong Kong / Гонконг</option>
                    <option value="Nhava Sheva / Нава Шева">Nhava Sheva / Нава Шева</option>
                    <option value="Singapore / Сингапур">Singapore / Сингапур</option>
                </select>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div class="form-group">
                    <label for="container_type">Тип контейнера *</label>
                    <select class="form-control" id="container_type" name="container_type" required>
                        <?php foreach ($transport_params['sea']['containers'] as $key => $name) { ?>
                            <option value="<?= $key ?>"><?= $name ?></option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="exchange_rate">Курс USD/RUB</label>
                    <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" 
                           step="0.01" value="<?= $_GET['exchange_rate'] ?? 75 ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Дополнительные расходы</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                    <?php foreach ($transport_params['sea']['additional_costs'] as $key => $name) { ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="additional[]" value="<?= $key ?>"
                                   <?= in_array($key, $_GET['additional'] ?? []) ? 'checked' : '' ?>>
                            <span><?= $name ?></span>
                        </label>
                    <?php } ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="vladivostok_transport">Перевозка до/из Владивостока (руб)</label>
                <input type="number" class="form-control" id="vladivostok_transport" name="vladivostok_transport" 
                       min="0" step="100" value="<?= $_GET['vladivostok_transport'] ?? 0 ?>">
                <small style="color: var(--text-secondary);">Стоимость доставки груза до порта Владивосток и от него</small>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; margin-top: 20px;">
                <span style="font-size: 16px;">Рассчитать стоимость</span>
            </button>
        </form>
        
        <?php } elseif (($_GET['calculation_type'] ?? 'sea') === 'rail') { ?>
        <!-- Форма для ЖД перевозок -->
        <form method="GET" action="?module=rates_calculator">
            <input type="hidden" name="calculate" value="1">
            <input type="hidden" name="calculation_type" value="rail">
            
            <div class="form-group">
                <label for="station_to">Станция назначения *</label>
                <select class="form-control" id="station_to" name="station_to" required>
                    <option value="">Выберите станцию</option>
                    <option value="Силикатная / Белый Раст/ Чехов (Москва)">Силикатная / Белый Раст/ Чехов (Москва)</option>
                    <option value="Новосибирск-Восточный (Новосибирск)">Новосибирск-Восточный (Новосибирск)</option>
                    <option value="Кольцово (Екатеринбург)">Кольцово (Екатеринбург)</option>
                    <option value="Автово (Санкт-Петербург)">Автово (Санкт-Петербург)</option>
                    <option value="Базаиха (Красноярск)">Базаиха (Красноярск)</option>
                </select>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div class="form-group">
                    <label for="container_type">Тип контейнера *</label>
                    <select class="form-control" id="container_type" name="container_type" required>
                        <?php foreach ($transport_params['rail']['containers'] as $key => $name) { ?>
                            <option value="<?= $key ?>"><?= $name ?></option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="weight">Вес груза (тонны)</label>
                    <input type="number" class="form-control" id="weight" name="weight" 
                           min="0" step="0.1" value="<?= $_GET['weight'] ?? 0 ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="security" value="1"
                           <?= isset($_GET['security']) ? 'checked' : '' ?>>
                    <span>Организация охраны груза</span>
                </label>
            </div>
            
            <div class="form-group">
                <label for="station_transport">Доставка до/от станции (руб)</label>
                <input type="number" class="form-control" id="station_transport" name="station_transport" 
                       min="0" step="100" value="<?= $_GET['station_transport'] ?? 0 ?>">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; margin-top: 20px;">
                <span style="font-size: 16px;">Рассчитать стоимость</span>
            </button>
        </form>
        
        <?php } elseif (($_GET['calculation_type'] ?? 'sea') === 'truck') { ?>
        <!-- Форма для авто перевозок -->
        <form method="GET" action="?module=rates_calculator">
            <input type="hidden" name="calculate" value="1">
            <input type="hidden" name="calculation_type" value="truck">
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="border_point">Пограничный пункт *</label>
                    <select class="form-control" id="border_point" name="border_point" required>
                        <option value="Alashankou">Алашанькоу</option>
                        <option value="Manzhouli">Маньчжурия</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="origin_city">Город отправления (Китай) *</label>
                    <select class="form-control" id="origin_city" name="origin_city" required>
                        <option value="Guangzhou">Гуанчжоу</option>
                        <option value="Xiameng">Сямынь</option>
                        <option value="Shanghai">Шанхай</option>
                        <option value="Tianjin">Тяньцзинь</option>
                        <option value="Ningbo">Нинбо</option>
                        <option value="Qingdao">Циндао</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="destination">Город назначения (Россия) *</label>
                    <select class="form-control" id="destination" name="destination" required>
                        <option value="Novosibirsk">Новосибирск</option>
                        <option value="Yekaterinburg">Екатеринбург</option>
                        <option value="Moscow">Москва</option>
                        <option value="S.Petersburg">Санкт-Петербург</option>
                        <option value="Minsk">Минск</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="loading_type">Тип погрузки</label>
                    <select class="form-control" id="loading_type" name="loading_type">
                        <option value="pallet">Паллеты</option>
                        <option value="box">Коробки</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="truck_type">Тип автомашины</label>
                    <select class="form-control" id="truck_type" name="truck_type">
                        <?php foreach ($transport_params['truck']['truck_types'] as $key => $name) { ?>
                            <option value="<?= $key ?>"><?= $name ?></option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="exchange_rate">Курс CNY/RUB</label>
                    <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" 
                           step="0.01" value="<?= $_GET['exchange_rate'] ?? 11.5 ?>" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; margin-top: 20px;">
                <span style="font-size: 16px;">Рассчитать стоимость</span>
            </button>
        </form>
        
        <?php } else { ?>
        <!-- Форма для мультимодальных перевозок -->
        <div style="text-align: center; padding: 40px 20px;">
            <div style="font-size: 48px; color: var(--primary-color); margin-bottom: 20px;">🚚+🚢+🚆</div>
            <h3>Мультимодальные перевозки</h3>
            <p style="color: var(--text-secondary); margin-bottom: 30px;">
                Комбинированный расчет с использованием нескольких видов транспорта
            </p>
            <button class="btn btn-primary" onclick="showMultimodalModal()">
                Начать расчет
            </button>
        </div>
        <?php } ?>
    </div>
    
    <!-- Результаты расчета -->
    <?php if (isset($result)) { ?>
        <div class="card scoring-result-card">
            <h3 style="margin-bottom: 20px;">Результаты расчета</h3>
            
            <?php if (isset($result['error'])) { ?>
                <div class="alert alert-danger">
                    <?= $result['error'] ?>
                </div>
            <?php } else { ?>
                <div class="details-grid">
                    <?php if (isset($result['total_usd'])) { ?>
                        <div class="detail-card">
                            <div class="detail-header">
                                <span style="display: inline-block; width: 24px; height: 24px; background: var(--primary-color); 
                                      mask: url('data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"%3E%3Cpath d=\"M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z\'/%3E%3C/svg%3E'); 
                                      -webkit-mask: url('data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"%3E%3Cpath d=\"M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z\'/%3E%3C/svg%3E');"></span>
                                <h6>Стоимость в USD</h6>
                            </div>
                            <div style="font-size: 28px; font-weight: bold; color: var(--primary-color);">
                                $<?= number_format($result['total_usd'], 2, '.', ' ') ?>
                            </div>
                        </div>
                    <?php } ?>
                    
                    <div class="detail-card">
                        <div class="detail-header">
                            <span style="display: inline-block; width: 24px; height: 24px; background: #4CAF50; 
                                  mask: url('data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"%3E%3Cpath d=\"M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z\'/%3E%3C/svg%3E'); 
                                  -webkit-mask: url('data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\"%3E%3Cpath d=\"M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z\'/%3E%3C/svg%3E');"></span>
                            <h6>Стоимость в RUB</h6>
                        </div>
                        <div style="font-size: 32px; font-weight: bold; color: #4CAF50;">
                            <?= number_format($result['total_rub'] ?? $result['total_usd'] * ($_GET['exchange_rate'] ?? 75), 2, '.', ' ') ?> ₽
                        </div>
                    </div>
                </div>
                
                <?php if (isset($result['breakdown'])) { ?>
                    <div style="margin-top: 25px;">
                        <h4 style="margin-bottom: 15px;">Детализация расходов</h4>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Статья расходов</th>
                                        <th>Сумма</th>
                                        <th>Валюта</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['breakdown'] as $item) { ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                            <td><?= number_format($item['amount'], 2, '.', ' ') ?></td>
                                            <td><?= $item['currency'] ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>
                
                <div style="background: rgba(255, 87, 34, 0.1); border: 2px solid var(--primary-color); 
                     border-radius: 10px; padding: 20px; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="margin: 0;">Итого к оплате</h4>
                            <small style="color: var(--text-secondary);">С учетом всех расходов</small>
                        </div>
                        <div style="font-size: 36px; font-weight: bold; color: white;">
                            <?= number_format($result['total_rub'] ?? $result['total_usd'] * ($_GET['exchange_rate'] ?? 75), 2, '.', ' ') ?> ₽
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 15px;">
                    <button class="btn btn-primary" onclick="window.print()">
                        <span>Распечатать расчет</span>
                    </button>
                    <button class="btn btn-secondary" onclick="saveCalculation()">
                        <span>Сохранить расчет</span>
                    </button>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<?php if ($user['role'] === 'admin') { ?>
<div class="card form-container" style="margin-top: 30px;">
    <h3 style="margin-bottom: 25px;">Управление тарифами</h3>
    
    <div class="tabs" style="display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 20px;">
        <?php foreach ($transport_types as $key => $name) { ?>
            <button class="tab-btn <?= ($_GET['tariff_tab'] ?? 'sea') === $key ? 'active' : '' ?>"
                    onclick="window.location.href='?module=rates_calculator&tariff_tab=<?= $key ?>'"
                    style="padding: 10px 20px; background: none; border: none; color: var(--text-secondary); 
                           cursor: pointer; font-weight: 600; border-bottom: 3px solid transparent;
                           transition: var(--transition);">
                <?= $name ?>
            </button>
        <?php } ?>
    </div>
    
    <div style="padding: 20px 0;">
        <form method="POST" enctype="multipart/form-data" 
              style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 15px; align-items: end;">
            <input type="hidden" name="transport_type" value="<?= $_GET['tariff_tab'] ?? 'sea' ?>">
            
            <div class="form-group">
                <label>Тип тарифа</label>
                <div style="padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                    <strong><?= $transport_types[$_GET['tariff_tab'] ?? 'sea'] ?></strong>
                </div>
            </div>
            
            <div class="form-group">
                <label for="tariff_file">Файл Excel с тарифами</label>
                <input type="file" class="form-control" id="tariff_file" name="tariff_file" 
                       accept=".xlsx,.xls" required>
                <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                    Формат должен соответствовать шаблону для <?= $transport_types[$_GET['tariff_tab'] ?? 'sea'] ?>
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary" style="height: 42px;">
                Загрузить
            </button>
        </form>
        
        <?php
        $current_tariffs = loadTariffs($_GET['tariff_tab'] ?? 'sea');
    if ($current_tariffs) {
        ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                <h4>Текущие тарифы</h4>
                <div style="max-height: 300px; overflow-y: auto; margin-top: 15px;">
                    <pre style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; 
                         color: var(--text-secondary); font-size: 12px;">
<?= json_encode($current_tariffs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>
                    </pre>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
<?php } ?>

<!-- Модальное окно для мультимодальных перевозок -->
<div id="multimodalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
     background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: var(--card-bg); border-radius: 15px; width: 90%; max-width: 800px; max-height: 90vh; 
         overflow-y: auto; padding: 30px; border: 2px solid var(--primary-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h3 style="margin: 0;">Конструктор мультимодальной перевозки</h3>
            <button onclick="hideMultimodalModal()" style="background: none; border: none; color: var(--text-secondary); 
                    font-size: 24px; cursor: pointer;">×</button>
        </div>
        
        <div id="multimodalComponents">
            <!-- Компоненты будут добавляться здесь -->
        </div>
        
        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <button class="btn btn-secondary" onclick="addComponent('truck')">
                + Автоперевозка
            </button>
            <button class="btn btn-secondary" onclick="addComponent('sea')">
                + Морская перевозка
            </button>
            <button class="btn btn-secondary" onclick="addComponent('rail')">
                + ЖД перевозка
            </button>
        </div>
        
        <div style="margin-top: 30px; text-align: right;">
            <button class="btn btn-primary" onclick="calculateMultimodal()">
                Рассчитать общую стоимость
            </button>
        </div>
    </div>
</div>

<style>
.tab-btn.active {
    color: var(--primary-color) !important;
    border-bottom-color: var(--primary-color) !important;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid transparent;
}
.alert-success {
    background: rgba(76, 175, 80, 0.15);
    border-color: rgba(76, 175, 80, 0.3);
    color: #4CAF50;
}
.alert-danger {
    background: rgba(244, 67, 54, 0.15);
    border-color: rgba(244, 67, 54, 0.3);
    color: #f44336;
}
</style>

<script>
function showMultimodalModal() {
    document.getElementById('multimodalModal').style.display = 'flex';
}

function hideMultimodalModal() {
    document.getElementById('multimodalModal').style.display = 'none';
}

function addComponent(type) {
    const container = document.getElementById('multimodalComponents');
    const componentId = 'component_' + Date.now();
    
    let title = '';
    let fields = '';
    
    switch(type) {
        case 'truck':
            title = 'Автоперевозка';
            fields = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <input type="text" placeholder="Город отправления" class="form-control">
                    <input type="text" placeholder="Город назначения" class="form-control">
                </div>
            `;
            break;
        case 'sea':
            title = 'Морская перевозка';
            fields = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <select class="form-control">
                        <option value="">Порт отправления</option>
                        <option>Читтагонг</option>
                        <option>Гонконг</option>
                    </select>
                    <select class="form-control">
                        <option value="">Тип контейнера</option>
                        <option>20'DC</option>
                        <option>40'HC</option>
                    </select>
                </div>
            `;
            break;
        case 'rail':
            title = 'ЖД перевозка';
            fields = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <input type="text" placeholder="Станция отправления" class="form-control">
                    <input type="text" placeholder="Станция назначения" class="form-control">
                </div>
            `;
            break;
    }
    
    const component = document.createElement('div');
    component.id = componentId;
    component.style.cssText = `
        background: rgba(255,255,255,0.03); 
        border: 1px solid var(--border-color); 
        border-radius: 10px; 
        padding: 20px; 
        margin-bottom: 15px;
    `;
    
    component.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
            <div>
                <h5 style="margin: 0 0 5px 0;">${title}</h5>
                <small style="color: var(--text-secondary);">Компонент перевозки</small>
            </div>
            <button onclick="removeComponent('${componentId}')" 
                    style="background: none; border: none; color: var(--text-secondary); cursor: pointer;">
                Удалить
            </button>
        </div>
        ${fields}
        <input type="hidden" name="components[][type]" value="${type}">
    `;
    
    container.appendChild(component);
}

function removeComponent(id) {
    document.getElementById(id).remove();
}

function calculateMultimodal() {
    alert('Функция мультимодального расчета находится в разработке');
}

function saveCalculation() {
    const calculationData = {
        type: '<?= $_GET['calculation_type'] ?? 'sea' ?>',
        result: <?= json_encode($result ?? []) ?>,
        params: <?= json_encode($_GET) ?>,
        timestamp: new Date().toISOString(),
        user_id: <?= $user['id'] ?>
    };
    
    fetch('api/save_calculation.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(calculationData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Расчет сохранен в истории');
        } else {
            alert('Ошибка при сохранении');
        }
    });
}
</script>