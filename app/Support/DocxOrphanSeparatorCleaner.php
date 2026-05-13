<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * После подстановки пустых плейсхолдеров в DOCX часто остаются ведущие «, », «; » и т.п. в первом {@code w:t} абзаца.
 * Убираем только ведущие разделители/пробелы до первого «содержательного» символа в каждом {@code w:p}.
 */
final class DocxOrphanSeparatorCleaner
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public static function cleanWordprocessingMl(string $documentXml): string
    {
        if (! str_contains($documentXml, '<w:t')) {
            return $documentXml;
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($documentXml, LIBXML_NONET | LIBXML_COMPACT)) {
            return $documentXml;
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);

        foreach ($xp->query('//w:p') as $p) {
            if (! $p instanceof DOMElement) {
                continue;
            }

            self::cleanParagraph($p, $xp);
        }

        return self::saveXmlPreservingDeclaration($documentXml, $dom);
    }

    private static function cleanParagraph(DOMElement $p, DOMXPath $xp): void
    {
        $maxPasses = 32;
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $changed = false;
            $nodes = $xp->query('.//w:t', $p);
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $text = $node->textContent;
                if ($text === '') {
                    continue;
                }

                $collapsed = preg_replace('/,\s*,+/u', ', ', $text) ?? $text;
                if ($collapsed !== $text) {
                    self::setTextNodeContent($node, $collapsed);
                    $text = $collapsed;
                    $changed = true;
                }

                $stripped = preg_replace('/^[\p{Z}\p{C},;:–\-]+/u', '', $text) ?? $text;
                if ($stripped !== $text) {
                    self::setTextNodeContent($node, $stripped);
                    $changed = true;
                }

                if ($stripped !== '' && preg_match('/\S/u', $stripped) === 1) {
                    return;
                }
            }

            if (! $changed) {
                return;
            }
        }
    }

    private static function setTextNodeContent(DOMElement $wt, string $text): void
    {
        while ($wt->firstChild !== null) {
            $wt->removeChild($wt->firstChild);
        }

        $wt->appendChild($wt->ownerDocument->createTextNode($text));

        if ($text !== '' && (preg_match('/^\s/u', $text) === 1 || preg_match('/\s$/u', $text) === 1)) {
            $wt->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        } else {
            if ($wt->hasAttribute('xml:space')) {
                $wt->removeAttribute('xml:space');
            }
        }
    }

    private static function saveXmlPreservingDeclaration(string $original, DOMDocument $dom): string
    {
        if (preg_match('/^\s*<\?xml[^>]*>\s*/', $original, $matches) !== 1) {
            return (string) $dom->saveXML();
        }

        return $matches[0].$dom->saveXML($dom->documentElement);
    }
}
