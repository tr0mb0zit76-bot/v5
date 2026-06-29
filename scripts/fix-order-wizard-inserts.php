<?php

$path = __DIR__.'/../tests/Feature/Orders/OrderWizardTest.php';
$content = file_get_contents($path);
$content = str_replace('DB::table(\'orders\')->insertGetId([', '$this->insertOrderRow([', $content);
file_put_contents($path, $content);
echo "OrderWizardTest.php: replaced order inserts\n";
