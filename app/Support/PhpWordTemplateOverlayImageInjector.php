<?php

namespace App\Support;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Вставка картинок в DOCX-шаблон через {@see TemplateProcessor::setImageValue}.
 *
 * PhpWord 1.4: при лимите по умолчанию (-1) внутри setImageValue срабатывает `++$i >= -1` и цикл
 * обрывается после первого совпадения, если в документе несколько одинаковых плейсхолдеров.
 * При передаче большого лимита (например PHP_INT_MAX) PhpWord переключается на preg_replace вместо
 * str_replace — тогда символы `$` в XML замены трактуются как обратные ссылки и ломают VML.
 *
 * Поэтому: несколько вызовов setImageValue с лимитом по умолчанию — по числу вхождений
 * {@see self::countPlaceholderMacros} до вставки (PhpWord уменьшает счётчик после каждой замены).
 */
final class PhpWordTemplateOverlayImageInjector
{
    /**
     * @param  array{path: string, width: int, height: int, ratio: bool}  $imagePayload
     */
    public static function injectImageForAllMacroStyles(
        TemplateProcessor $processor,
        string $innerPlaceholder,
        array $imagePayload,
        int $maxPasses = 48,
    ): void {
        foreach ([['${', '}'], ['{{', '}}']] as $pair) {
            [$open, $close] = $pair;
            $processor->setMacroChars($open, $close);
            DocxTextRunPlaceholderMerger::applyToTemplateProcessor($processor, $open, $close, $innerPlaceholder);

            $initial = self::countPlaceholderMacros($processor, $innerPlaceholder);
            $passes = min($initial, $maxPasses);

            for ($pass = 0; $pass < $passes; $pass++) {
                $processor->setImageValue($innerPlaceholder, $imagePayload);
            }
        }

        $processor->setMacroChars('${', '}');
    }

    public static function countPlaceholderMacros(TemplateProcessor $processor, string $innerPlaceholder): int
    {
        $counts = $processor->getVariableCount();
        $total = 0;

        foreach ($counts as $name => $count) {
            if (! is_string($name)) {
                continue;
            }

            if ($name === $innerPlaceholder || str_starts_with($name, $innerPlaceholder.':')) {
                $total += (int) $count;
            }
        }

        return $total;
    }
}
