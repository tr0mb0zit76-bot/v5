<?php

namespace App\Support;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Word часто режет плейсхолдер на несколько w:r/w:t; PhpWord {@see TemplateProcessor::setImageValue}
 * ищет макрос одним фрагментом внутри одной пары тегов, из‑за чего замена ломает XML или не срабатывает.
 */
final class DocxTextRunPlaceholderMerger
{
    /**
     * Склеивает разбитый макрос в первом w:t смежной пары run'ов (типичное разбиение Word).
     */
    public static function mergePlaceholderAcrossAdjacentRuns(string $xml, string $open, string $close, string $inner): string
    {
        if ($inner === '') {
            return $xml;
        }

        $full = $open.$inner.$close;
        if (str_contains($xml, $full)) {
            return $xml;
        }

        $q = preg_quote($inner, '#');
        $betweenRuns = '(?:\s|<w:proofErr[^>]*/>)*';

        for ($pass = 0; $pass < 32; $pass++) {
            if (str_contains($xml, $full)) {
                break;
            }

            $before = $xml;
            $count = 0;

            if ($open === '${' && $close === '}') {
                $xml = (string) preg_replace(
                    '#(<w:t(?:\s[^>]*)?>)\s*\$\s*</w:t></w:r>'.$betweenRuns.'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?'.$betweenRuns.'<w:t(?:\s[^>]*)?>\s*\{\s*'.$q.'\s*\}\s*</w:t></w:r>#sU',
                    '\\1${'.$inner.'}</w:t></w:r>',
                    $xml,
                    -1,
                    $count
                );
                if ($count > 0) {
                    continue;
                }

                $xml = (string) preg_replace(
                    '#(<w:t(?:\s[^>]*)?>)\s*\$\{\s*</w:t></w:r>'.$betweenRuns.'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?'.$betweenRuns.'<w:t(?:\s[^>]*)?>\s*'.$q.'\s*\}\s*</w:t></w:r>#sU',
                    '\\1${'.$inner.'}</w:t></w:r>',
                    $xml,
                    -1,
                    $count
                );
                if ($count > 0) {
                    continue;
                }

                $xml = (string) preg_replace(
                    '#(<w:t(?:\s[^>]*)?>)\s*\$\{\s*</w:t></w:r>'.$betweenRuns.'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?'.$betweenRuns.'<w:t(?:\s[^>]*)?>\s*'.$q.'\s*</w:t></w:r>'.$betweenRuns.'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?'.$betweenRuns.'<w:t(?:\s[^>]*)?>\s*\}\s*</w:t></w:r>#sU',
                    '\\1${'.$inner.'}</w:t></w:r>',
                    $xml,
                    -1,
                    $count
                );
            } elseif ($open === '{{' && $close === '}}') {
                $xml = (string) preg_replace(
                    '#(<w:t(?:\s[^>]*)?>)\s*\{\s*</w:t></w:r>'.$betweenRuns.'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?'.$betweenRuns.'<w:t(?:\s[^>]*)?>\s*\{\s*'.$q.'\s*\}\s*\}\s*</w:t></w:r>#sU',
                    '\\1{{'.$inner.'}}</w:t></w:r>',
                    $xml,
                    -1,
                    $count
                );
                if ($count > 0) {
                    continue;
                }

                $xml = (string) preg_replace(
                    '#(<w:t(?:\s[^>]*)?>)\s*\{\{\s*</w:t></w:r>'.$betweenRuns.'<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?'.$betweenRuns.'<w:t(?:\s[^>]*)?>\s*'.$q.'\s*\}\s*\}\s*</w:t></w:r>#sU',
                    '\\1{{'.$inner.'}}</w:t></w:r>',
                    $xml,
                    -1,
                    $count
                );
            }

            if ($xml === $before) {
                break;
            }
        }

        return $xml;
    }

    public static function applyToTemplateProcessor(TemplateProcessor $processor, string $open, string $close, string $inner): void
    {
        $ref = new \ReflectionClass($processor);

        $main = $ref->getProperty('tempDocumentMainPart');
        $main->setAccessible(true);
        $main->setValue(
            $processor,
            self::mergePlaceholderAcrossAdjacentRuns((string) $main->getValue($processor), $open, $close, $inner)
        );

        foreach (['tempDocumentHeaders', 'tempDocumentFooters'] as $propName) {
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $parts = $prop->getValue($processor);
            if (! is_array($parts)) {
                continue;
            }
            foreach ($parts as $idx => $partXml) {
                $parts[$idx] = self::mergePlaceholderAcrossAdjacentRuns((string) $partXml, $open, $close, $inner);
            }
            $prop->setValue($processor, $parts);
        }
    }
}
