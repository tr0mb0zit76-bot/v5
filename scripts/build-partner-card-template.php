<?php

/**
 * Однократная сборка DOCX-шаблона «Карта партнёра» для PhpWord TemplateProcessor.
 * php scripts/build-partner-card-template.php
 */

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

$out = __DIR__.'/../resources/templates/contractors/partner-card.docx';
@mkdir(dirname($out), 0775, true);

$phpWord = new PhpWord;
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

$section = $phpWord->addSection([
    'marginTop' => 1134,
    'marginBottom' => 1134,
    'marginLeft' => 1134,
    'marginRight' => 1134,
]);

$section->addText('${kp_header_line1}', ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
$section->addText('«${kp_header_name}»', ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);
$section->addText('Карточка основных сведений об организации', ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$table = $section->addTable([
    'borderSize' => 4,
    'borderColor' => '000000',
    'cellMargin' => 80,
]);

$rows = [
    ['Полное наименование организации', '${kp_full_name}'],
    ['Сокращенное наименование организации', '${kp_short_name}'],
    ['Юридический адрес', '${kp_legal_address}'],
    ['Почтовый адрес', '${kp_postal_address}'],
    ['ОГРН', '${kp_ogrn}'],
    ['ОКВЭД', '${kp_okved}'],
    ['ИНН', '${kp_inn}'],
    ['КПП', '${kp_kpp}'],
    ['Расчетный счет, рубли', '${kp_rs_rub}'],
    ['Расчётный счёт, юани', '${kp_rs_cny}'],
    ['Наименование банковского учреждения', '${kp_bank}'],
    ['Корреспондентский счет', '${kp_ks}'],
    ['БИК', '${kp_bik}'],
    ['Должность руководителя', '${kp_ceo_title}'],
    ['Ф.И.О. руководителя', '${kp_ceo_fio}'],
    ['Документ, на основании которого действует руководитель', '${kp_ceo_basis}'],
    ['Телефон/факс', '${kp_phone}'],
    ['E-mail', '${kp_email}'],
    ['Провайдер ЭДО', '${kp_edo_provider}'],
    ['Номер в ЭДО', '${kp_edo_number}'],
];

foreach ($rows as [$label, $macro]) {
    $table->addRow();
    $labelCell = $table->addCell(3200);
    $labelCell->addText($label, ['size' => 11]);
    $valueCell = $table->addCell(5600);
    $valueCell->addText($macro, ['size' => 11]);
}

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($out);

echo "Written: {$out}\n";
