<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Word часто режет плейсхолдер на несколько w:r/w:t; PhpWord {@see TemplateProcessor::setImageValue}
 * ищет макрос одним фрагментом внутри одной пары тегов, из‑за чего замена ломает XML или не срабатывает.
 */
final class DocxTextRunPlaceholderMerger
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Склеивает любые ${…}, разбитые Word на несколько w:r/w:t внутри одного w:p.
     */
    public static function mergeAllSplitDollarMacrosInXml(string $xml): string
    {
        if (! str_contains($xml, '${')) {
            return $xml;
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return $xml;
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);

        foreach ($xp->query('//w:p') as $paragraph) {
            if ($paragraph instanceof DOMElement) {
                self::mergeSplitDollarMacrosInParagraph($paragraph, $xp);
            }
        }

        return self::saveXmlPreservingDeclaration($xml, $dom);
    }

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

    private static function mergeSplitDollarMacrosInParagraph(DOMElement $paragraph, DOMXPath $xp): void
    {
        $runs = [];
        foreach ($xp->query('./w:r', $paragraph) as $run) {
            if ($run instanceof DOMElement) {
                $runs[] = $run;
            }
        }

        $index = 0;
        while ($index < count($runs)) {
            $run = $runs[$index];
            $textNode = self::firstTextNode($run, $xp);

            if ($textNode === null) {
                $index++;

                continue;
            }

            $text = $textNode->textContent;

            if (! self::dollarMacroTailNeedsMoreRuns($text)) {
                $index++;

                continue;
            }

            $nextIndex = $index + 1;
            while ($nextIndex < count($runs) && self::dollarMacroTailNeedsMoreRuns($text)) {
                $nextRun = $runs[$nextIndex];
                $nextTextNode = self::firstTextNode($nextRun, $xp);

                if ($nextTextNode === null) {
                    break;
                }

                $text .= $nextTextNode->textContent;
                $paragraph->removeChild($nextRun);
                unset($runs[$nextIndex]);
                $runs = array_values($runs);
            }

            self::setTextNodeContent($textNode, $text);
            $index++;
        }
    }

    private static function firstTextNode(DOMElement $run, DOMXPath $xp): ?DOMElement
    {
        $nodes = $xp->query('./w:t', $run);

        if ($nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private static function setTextNodeContent(DOMElement $textNode, string $text): void
    {
        while ($textNode->firstChild !== null) {
            $textNode->removeChild($textNode->firstChild);
        }

        $textNode->appendChild($textNode->ownerDocument->createTextNode($text));

        if ($text !== '' && (preg_match('/^\s/u', $text) === 1 || preg_match('/\s$/u', $text) === 1)) {
            $textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        } elseif ($textNode->hasAttribute('xml:space')) {
            $textNode->removeAttribute('xml:space');
        }
    }

    private static function dollarMacroTailNeedsMoreRuns(string $text): bool
    {
        $openPos = strrpos($text, '${');

        if ($openPos === false) {
            return false;
        }

        return ! str_contains(substr($text, $openPos), '}');
    }

    private static function saveXmlPreservingDeclaration(string $original, DOMDocument $dom): string
    {
        if (preg_match('/^\s*<\?xml[^>]*>\s*/', $original, $matches) !== 1) {
            return (string) $dom->saveXML();
        }

        return $matches[0].$dom->saveXML($dom->documentElement);
    }
}
