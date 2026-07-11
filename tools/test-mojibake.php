<?php

function maybeFixMojibake(string $value): string
{
    if (! preg_match('/[ЁЯ╨]/u', $value)) {
        return $value;
    }

    $bytes = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    if (! is_string($bytes) || $bytes === '') {
        return $value;
    }

    $fixed = @mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1');

    return is_string($fixed) && $fixed !== '' ? $fixed : $value;
}

$lines = file(__DIR__.'/prod-existing-utf8.tsv');
foreach ($lines as $i => $line) {
    if (! str_contains($line, '╤В╨░╤А')) {
        continue;
    }
    [$parent, $title] = explode("\t", trim($line), 2);
    echo "line {$i}: {$title}\n";
    echo 'fixed: '.maybeFixMojibake($title)."\n";
    break;
}
