<?php

namespace App\Support;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * PhpWord cloneRow видит только «цельные» макросы в XML; CRM-extractor находит и разбитые.
 */
final class PrintFormTemplateProcessorPreparer
{
    /**
     * @param  list<string>  $placeholders
     */
    public static function repairTextMacros(TemplateProcessor $processor, array $placeholders): void
    {
        self::mergeAllSplitDollarMacrosInProcessor($processor);

        $innerNames = collect($placeholders)
            ->filter(static fn (mixed $placeholder): bool => is_string($placeholder) && trim($placeholder) !== '')
            ->map(static fn (string $placeholder): string => trim(explode('#', trim($placeholder))[0]))
            ->unique()
            ->values()
            ->all();

        foreach ($innerNames as $inner) {
            if ($inner === '') {
                continue;
            }

            DocxTextRunPlaceholderMerger::applyToTemplateProcessor($processor, '${', '}', $inner);
            DocxTextRunPlaceholderMerger::applyToTemplateProcessor($processor, '{{', '}}', $inner);
        }
    }

    /**
     * @param  list<string>  $innerMacroNames
     */
    public static function repairCloneRowMacros(TemplateProcessor $processor, array $innerMacroNames): void
    {
        $unique = array_values(array_unique(array_filter(array_map(
            static fn (mixed $name): string => trim(explode('#', trim((string) $name))[0]),
            $innerMacroNames,
        ))));

        foreach ($unique as $inner) {
            if ($inner === '') {
                continue;
            }

            DocxTextRunPlaceholderMerger::applyToTemplateProcessor($processor, '${', '}', $inner);
            DocxTextRunPlaceholderMerger::applyToTemplateProcessor($processor, '{{', '}}', $inner);
        }
    }

    /**
     * @param  list<string>  $placeholders
     * @return list<string>
     */
    public static function collectCloneRowMacrosFromPlaceholders(array $placeholders): array
    {
        return collect($placeholders)
            ->filter(static fn (mixed $placeholder): bool => is_string($placeholder) && trim($placeholder) !== '')
            ->filter(static fn (string $placeholder): bool => PrintFormBasicTermsTableCloner::isBasicTermsPlaceholder($placeholder)
                || PrintFormCargoTableCloner::isCargoTablePlaceholder($placeholder)
                || PrintFormRouteTableCloner::isRouteTablePlaceholder($placeholder))
            ->map(static fn (string $placeholder): string => trim(explode('#', trim($placeholder))[0]))
            ->unique()
            ->values()
            ->all();
    }

    public static function processorHasMacro(TemplateProcessor $processor, string $anchor): bool
    {
        return self::resolveProcessorMacro($processor, $anchor) !== null;
    }

    public static function mergeAllSplitDollarMacrosInProcessor(TemplateProcessor $processor): void
    {
        $ref = new \ReflectionClass($processor);

        $main = $ref->getProperty('tempDocumentMainPart');
        $main->setAccessible(true);
        $main->setValue(
            $processor,
            DocxTextRunPlaceholderMerger::mergeAllSplitDollarMacrosInXml((string) $main->getValue($processor))
        );

        foreach (['tempDocumentHeaders', 'tempDocumentFooters'] as $propName) {
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $parts = $prop->getValue($processor);
            if (! is_array($parts)) {
                continue;
            }
            foreach ($parts as $idx => $partXml) {
                $parts[$idx] = DocxTextRunPlaceholderMerger::mergeAllSplitDollarMacrosInXml((string) $partXml);
            }
            $prop->setValue($processor, $parts);
        }
    }

    public static function resolveProcessorMacro(TemplateProcessor $processor, string $anchor): ?string
    {
        $anchor = trim($anchor);

        if ($anchor === '') {
            return null;
        }

        foreach ($processor->getVariables() as $variable) {
            $name = trim((string) $variable);
            $base = trim(explode('#', $name)[0]);

            if ($base === $anchor) {
                return $base;
            }
        }

        return null;
    }
}
