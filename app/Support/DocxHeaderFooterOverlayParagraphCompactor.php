<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * PhpWord вставляет подпись/печать как inline VML внутри {@code w:p}; высота строки тянется за картинкой.
 * В колонтитулах задаём минимальную высоту строки абзаца (Word всё ещё рисует фигуру поверх/снаружи потока).
 */
final class DocxHeaderFooterOverlayParagraphCompactor
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public static function patch(string $documentXml, string $partPath): string
    {
        if (! str_starts_with($partPath, 'word/header') && ! str_starts_with($partPath, 'word/footer')) {
            return $documentXml;
        }

        if (! str_contains($documentXml, '#_x0000_t75')) {
            return $documentXml;
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($documentXml, LIBXML_NONET | LIBXML_COMPACT)) {
            return $documentXml;
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);
        $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

        foreach ($xp->query('//w:p[.//v:shape[contains(@type, "#_x0000_t75")]]') as $p) {
            if (! $p instanceof DOMElement) {
                continue;
            }

            self::ensureMinimalLineSpacing($dom, $p, $xp);
        }

        return self::saveXmlPreservingDeclaration($documentXml, $dom);
    }

    private static function ensureMinimalLineSpacing(DOMDocument $dom, DOMElement $p, DOMXPath $xp): void
    {
        $pPrList = $xp->query('./w:pPr', $p);
        $pPr = $pPrList->length > 0 ? $pPrList->item(0) : null;

        if (! $pPr instanceof DOMElement) {
            $pPr = $dom->createElementNS(self::W_NS, 'w:pPr');
            if ($p->firstChild !== null) {
                $p->insertBefore($pPr, $p->firstChild);
            } else {
                $p->appendChild($pPr);
            }
        }

        foreach ($xp->query('./w:spacing', $pPr) as $existing) {
            if (! $existing instanceof DOMElement) {
                continue;
            }

            if (! $existing->hasAttribute('w:line')) {
                $existing->setAttribute('w:line', '20');
                $existing->setAttribute('w:lineRule', 'exact');
            }
            $existing->setAttribute('w:before', '0');
            $existing->setAttribute('w:after', '0');

            return;
        }

        $spacing = $dom->createElementNS(self::W_NS, 'w:spacing');
        $spacing->setAttribute('w:before', '0');
        $spacing->setAttribute('w:after', '0');
        $spacing->setAttribute('w:line', '20');
        $spacing->setAttribute('w:lineRule', 'exact');
        $pPr->insertBefore($spacing, $pPr->firstChild);
    }

    private static function saveXmlPreservingDeclaration(string $original, DOMDocument $dom): string
    {
        if (preg_match('/^\s*<\?xml[^>]*>\s*/', $original, $matches) !== 1) {
            return (string) $dom->saveXML();
        }

        return $matches[0].$dom->saveXML($dom->documentElement);
    }
}
