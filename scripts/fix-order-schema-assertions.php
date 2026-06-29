<?php

declare(strict_types=1);

$files = [
    dirname(__DIR__).'/tests/Feature/Orders/OrderIndexTest.php',
    dirname(__DIR__).'/tests/Feature/Orders/OrderWizardTest.php',
];

foreach ($files as $path) {
    $content = file_get_contents($path);
    $original = $content;

    $content = str_replace("\$this->assertDatabaseHas('orders',", '$this->assertDatabaseHasOrder(', $content);
    $content = preg_replace(
        '/\(int\) DB::table\(\'orders\'\)->insertGetId\(\[/',
        '$this->insertOrderRow([',
        $content,
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo basename($path)." updated\n";
    }
}

echo "Done.\n";
