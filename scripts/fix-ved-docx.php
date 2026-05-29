<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Support\DocxTextRunPlaceholderMerger;

$sourceDocx = __DIR__.'/../public/change/Заявка с перевозчиком ВЭД.docx.bak';
$outputDocx = __DIR__.'/../public/change/Заявка с перевозчиком ВЭД.docx';

copy($sourceDocx, $outputDocx);

$zip = new ZipArchive;
$zip->open($outputDocx);
$xml = (string) $zip->getFromName('word/document.xml');
$originalLength = strlen($xml);

/** @var array<string, string> $macroReplacements */
$macroReplacements = [
    'cargo_name1' => 'cargo.lines_multiline',
    'kod_TN_VED' => 'cargo.hs_codes',
    'invojs' => 'order.invoice_number',
    'obschiy_ves_kg' => 'cargo.total_weight',
    'vsego_mest' => 'cargo.total_packages',
    'signature' => 'internal_signature_image',
    'stamp' => 'internal_stamp_image',
    'lp_EDO_prov' => 'provayder_edo',
    'lp_EDO_nomer' => 'nomer_edo',
];

/** @var list<string> $removeMacros */
$removeMacros = [
    'cargo_name2', 'cargo_name3', 'cargo_name4', 'cargo_name5',
    'gruz_1', 'gruz_2', 'gruz_3', 'gruz_4', 'gruz_5',
];

foreach (array_merge(array_keys($macroReplacements), $removeMacros) as $inner) {
    $xml = DocxTextRunPlaceholderMerger::mergePlaceholderAcrossAdjacentRuns($xml, '${', '}', $inner);
}

foreach ($macroReplacements as $from => $to) {
    $xml = str_replace('${'.$from.'}', '${'.$to.'}', $xml);

    if (! in_array($from, ['signature', 'stamp'], true)) {
        $xml = str_replace($from, $to, $xml);
    }
}

foreach ($removeMacros as $inner) {
    $xml = (string) preg_replace('#\$\{'.preg_quote($inner, '#').'\}#u', '', $xml);
}

/** @var array<string, string> $splitTypoPairs */
$splitTypoPairs = [
    '<w:t>pervoz</w:t></w:r><w:r><w:rPr><w:sz w:val="16"/><w:szCs w:val="16"/><w:lang w:val="en-US"/></w:rPr><w:t>_EDO_prov</w:t>' => '<w:t>provayder_edo_perev</w:t>',
    '<w:t>perevoz</w:t></w:r><w:r><w:rPr><w:sz w:val="16"/><w:szCs w:val="16"/><w:lang w:val="en-US"/></w:rPr><w:t>_EDO_nomer</w:t>' => '<w:t>nomer_edo_perev</w:t>',
    '<w:t>lp</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r w:rsidRPr="00C6168E"><w:rPr><w:sz w:val="16"/><w:szCs w:val="16"/><w:lang w:val="en-US"/></w:rPr><w:t xml:space="preserve">_ </w:t></w:r><w:proofErr w:type="spellStart"/><w:r w:rsidRPr="00C6168E"><w:rPr><w:sz w:val="16"/><w:szCs w:val="16"/><w:lang w:val="en-US"/></w:rPr><w:t>yur_address</w:t>' => '<w:t>customer.legal_address</w:t>',
];

foreach ($splitTypoPairs as $from => $to) {
    $xml = str_replace($from, $to, $xml);
}

foreach (['provayder_edo_perev', 'nomer_edo_perev', 'customer.legal_address'] as $inner) {
    $xml = DocxTextRunPlaceholderMerger::mergePlaceholderAcrossAdjacentRuns($xml, '${', '}', $inner);
}

$xml = removeRowIfPlainTextMatches($xml, static fn (string $plain): bool => trim($plain) === '');

if (! str_contains($xml, 'cargo_row_name')) {
    $xml = insertCargoRowAfterInvoiceRow($xml, buildCargoCloneRowXml());
}

if (strlen($xml) < (int) ($originalLength * 0.85)) {
    fwrite(STDERR, 'Abort: document.xml looks truncated ('.strlen($xml)." vs {$originalLength})\n");
    exit(1);
}

$zip->addFromString('word/document.xml', $xml);
$zip->close();

echo "Updated: {$outputDocx}\n";

function removeRowIfPlainTextMatches(string $xml, callable $matcher): string
{
    return (string) preg_replace_callback(
        '#<w:tr[^>]*>[\s\S]*?</w:tr>#u',
        static function (array $match) use ($matcher): string {
            $plain = html_entity_decode(preg_replace('/<[^>]+>/u', '', $match[0]) ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');

            return $matcher($plain) ? '' : $match[0];
        },
        $xml
    );
}

function buildCargoCloneRowXml(): string
{
    $cell = static function (string $placeholder, string $width): string {
        $content = $placeholder === ''
            ? ''
            : '<w:r><w:rPr><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr><w:t>${'.$placeholder.'}</w:t></w:r>';

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'
            .'<w:tcBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:tcBorders>'
            .'<w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:pStyle w:val="a9"/><w:rPr><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr></w:pPr>'
            .$content
            .'</w:p></w:tc>';
    };

    return '<w:tr w:rsidR="00FIXVED01" w14:paraId="FIXED0001" w14:textId="77777777">'
        .$cell('', '411')
        .$cell('', '2478')
        .$cell('', '2602')
        .$cell('cargo_row_name', '1583')
        .$cell('cargo_row_packages', '576')
        .$cell('cargo_row_weight', '709')
        .$cell('cargo_row_dimensions', '2268')
        .'</w:tr>';
}

function insertCargoRowAfterInvoiceRow(string $xml, string $cargoRow): string
{
    preg_match_all('#<w:tr[\s>][\s\S]*?</w:tr>#u', $xml, $rows, PREG_OFFSET_CAPTURE);
    $insertAt = null;

    foreach ($rows[0] as $match) {
        $plain = html_entity_decode(preg_replace('/<[^>]+>/u', '', $match[0]) ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
        if (str_contains($plain, 'инвойса') || str_contains($plain, 'order.invoice_number')) {
            $insertAt = $match[1] + strlen($match[0]);
        }
    }

    return $insertAt === null ? $xml : substr($xml, 0, $insertAt).$cargoRow.substr($xml, $insertAt);
}
