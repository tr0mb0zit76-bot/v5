<?php

namespace App\Support;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * В DOCX Word часто размечает плейсхолдеры с пробелами внутри ${ }: "${ inn}" vs "${inn}".
 * {@see TemplateProcessor::setValue} ищет точное совпадение строки в XML.
 */
final class PrintFormPlaceholderMacroVariants
{
    /**
     * Значения для setValue: внутренняя часть макроса (без обрамления), как ожидает PhpWord
     * после {@see TemplateProcessor::ensureMacroCompleted}.
     *
     * @return list<string>
     */
    public static function innerPartsForSetValue(string $trimmedPlaceholder): array
    {
        $t = trim($trimmedPlaceholder);
        if ($t === '') {
            return [];
        }

        return [$t];
    }
}
