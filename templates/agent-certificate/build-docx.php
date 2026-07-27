<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

$phpWord = new PhpWord;
$phpWord->getSettings()->setThemeFontLang(new Language(Language::RU_RU));
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'pageSizeW' => 16838,
    'pageSizeH' => 11906,
    'marginTop' => 700,
    'marginBottom' => 700,
    'marginLeft' => 900,
    'marginRight' => 900,
    'borderTopSize' => 24,
    'borderBottomSize' => 24,
    'borderLeftSize' => 24,
    'borderRightSize' => 24,
    'borderTopColor' => '1A2744',
    'borderBottomColor' => '1A2744',
    'borderLeftColor' => '1A2744',
    'borderRightColor' => '1A2744',
]);

$section->addText(
    'ИП «Милана»',
    ['bold' => true, 'size' => 18, 'color' => '1A2744', 'allCaps' => true],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
);
$section->addText(
    'Республика Казахстан · грузовые автомобильные перевозки',
    ['size' => 10, 'color' => '5A5A5A', 'italic' => true],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
);

$section->addText(
    'СЕРТИФИКАТ',
    ['bold' => true, 'size' => 28],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
);
$section->addText(
    'ОФИЦИАЛЬНОГО АГЕНТА',
    ['bold' => true, 'size' => 16, 'color' => '9B1C2E', 'allCaps' => true],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 280]
);

$section->addText(
    'Индивидуальный предприниматель «Милана» (ИИН/БИН ______________), Республика Казахстан, осуществляющий деятельность в сфере грузовых автомобильных перевозок, удостоверяет, что',
    ['italic' => true, 'size' => 12],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 160]
);

$section->addText(
    'ООО «АВТОАЛЬЯНС-СМОЛЕНСК»',
    ['bold' => true, 'size' => 18],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
);
$section->addText(
    'ИНН 6732110940 · г. Смоленск',
    ['bold' => true, 'size' => 12],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 160]
);

$section->addText(
    'является официальным агентом ИП «Милана» на территории Российской Федерации и уполномочено представлять интересы Принципала в сфере организации и сопровождения грузовых автомобильных перевозок.',
    ['italic' => true, 'size' => 12],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 140]
);

$section->addText(
    'Агент уполномочен осуществлять поиск и привлечение заказчиков перевозок, ведение переговоров, подготовку и сопровождение договорной документации, взаимодействие с грузоотправителями и грузополучателями, а также иные действия, связанные с представлением интересов ИП «Милана» на территории Российской Федерации, в пределах полномочий, предоставленных договором (соглашением) с Принципалом.',
    ['italic' => true, 'size' => 10, 'color' => '5A5A5A'],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 320]
);

$table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
$table->addRow();

$c1 = $table->addCell(4500);
$c1->addText('АВТОАЛЬЯНС', ['bold' => true, 'size' => 11, 'color' => '1A2744']);
$c1->addText('ООО «АВТОАЛЬЯНС-СМОЛЕНСК»', ['size' => 9, 'color' => '5A5A5A']);
$c1->addText('агент на территории РФ', ['size' => 9, 'color' => '5A5A5A']);

$c2 = $table->addCell(5500);
$c2->addText('Индивидуальный предприниматель', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
$c2->addTextBreak(2);
$c2->addText('______________________________', ['size' => 11], ['alignment' => Jc::CENTER]);
$c2->addText('подпись / ФИО полностью', ['size' => 9, 'color' => '5A5A5A', 'italic' => true], ['alignment' => Jc::CENTER]);
$c2->addText('Республика Казахстан', ['size' => 10], ['alignment' => Jc::CENTER]);

$c3 = $table->addCell(4500);
$c3->addText('Дата выдачи:', ['size' => 10, 'color' => '5A5A5A'], ['alignment' => Jc::END]);
$c3->addText('«____» ____________ 20____ г.', ['size' => 10], ['alignment' => Jc::END]);

$section->addTextBreak(1);
$section->addText(
    'Сертификат действителен до: «____» ____________ 20____ г.  ·  без приложения договора не является самостоятельным основанием полномочий',
    ['size' => 9, 'color' => '5A5A5A', 'italic' => true],
    ['alignment' => Jc::CENTER]
);

$out = __DIR__.'/sertifikat-agenta-milana.docx';
IOFactory::createWriter($phpWord, 'Word2007')->save($out);

echo "OK {$out}\n";
