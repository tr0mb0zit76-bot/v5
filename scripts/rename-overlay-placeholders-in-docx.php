<?php

declare(strict_types=1);

use App\Support\DocxTextRunPlaceholderMerger;
use App\Support\DocxVmlOverlayStylePatcher;

require __DIR__.'/../vendor/autoload.php';

/** @var list<string> $files */
$files = glob(__DIR__.'/../public/change/*.docx') ?: [];

/** @var array<string, string> $replacements */
$replacements = [
    'internal_signature_image' => 'signature',
    'internal_stamp_image' => 'stamp',
    'sign_image' => 'signature',
    'stmp_image' => 'stamp',
];

foreach ($files as $docxPath) {
    $zip = new ZipArchive;
    if ($zip->open($docxPath, ZipArchive::RDONLY) !== true) {
        fwrite(STDERR, "Skip (cannot open): {$docxPath}\n");

        continue;
    }

    $staging = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-rename-overlay-'.uniqid('', true).'.docx';
    copy($docxPath, $staging);
    $zip->close();

    $writeZip = new ZipArchive;
    if ($writeZip->open($staging, DocxVmlOverlayStylePatcher::zipOpenFlagsReadWrite()) !== true) {
        @unlink($staging);
        fwrite(STDERR, "Skip (cannot stage): {$docxPath}\n");

        continue;
    }

    $changed = false;

    for ($i = 0; $i < $writeZip->numFiles; $i++) {
        $name = $writeZip->getNameIndex($i);
        if (! is_string($name) || ! preg_match('#^word/(document|header[0-9]+|footer[0-9]+)\\.xml$#', $name)) {
            continue;
        }

        $xml = $writeZip->getFromName($name);
        if (! is_string($xml) || $xml === '') {
            continue;
        }

        $original = $xml;

        foreach ($replacements as $from => $to) {
            $xml = DocxTextRunPlaceholderMerger::mergePlaceholderAcrossAdjacentRuns($xml, '${', '}', $from);
            $xml = DocxTextRunPlaceholderMerger::mergePlaceholderAcrossAdjacentRuns($xml, '{{', '}}', $from);
            $xml = str_replace('${'.$from.'}', '${'.$to.'}', $xml);
            $xml = str_replace('{{'.$from.'}}', '{{'.$to.'}}', $xml);
        }

        if ($xml !== $original) {
            $writeZip->deleteName($name);
            $writeZip->addFromString($name, $xml);
            $changed = true;
        }
    }

    $writeZip->close();

    if ($changed) {
        copy($staging, $docxPath);
        echo basename($docxPath)." — updated\n";
    } else {
        echo basename($docxPath)." — no changes\n";
    }

    @unlink($staging);
}
