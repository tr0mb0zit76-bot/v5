<?php

namespace App\Services;

use App\Models\Contractor;
use App\Support\ContractorPartnerCardSnapshot;
use App\Support\PrintFormPlaceholderMacroVariants;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class ContractorPartnerCardService
{
    /**
     * @return array{disk: string, path: string, download_name: string}
     */
    public function generate(Contractor $contractor): array
    {
        if (! $contractor->is_own_company) {
            throw new \InvalidArgumentException('Карта партнёра доступна только для контрагентов с признаком «Своя компания».');
        }

        $templatePath = $this->resolveTemplatePath();
        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroChars('${', '}');

        foreach (ContractorPartnerCardSnapshot::replacements($contractor) as $placeholder => $replacement) {
            foreach (PrintFormPlaceholderMacroVariants::innerPartsForSetValue($placeholder) as $inner) {
                $processor->setValue($inner, $replacement);
            }
        }

        $disk = 'local';
        $safeName = Str::slug($contractor->name ?: 'partner-card');
        $downloadName = 'karta-partnera-'.($safeName !== '' ? $safeName : $contractor->id).'.docx';
        $storagePath = 'generated-documents/partner-cards/'.$contractor->id.'/'.Str::uuid().'-'.$downloadName;
        $absoluteTarget = Storage::disk($disk)->path($storagePath);
        $targetDirectory = dirname($absoluteTarget);

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create directory: %s', $targetDirectory));
        }

        $processor->saveAs($absoluteTarget);

        return [
            'disk' => $disk,
            'path' => $storagePath,
            'download_name' => $downloadName,
        ];
    }

    private function resolveTemplatePath(): string
    {
        $configured = config('partner_card.template_path');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $resource = resource_path('templates/contractors/partner-card.docx');
        if (is_file($resource)) {
            return $resource;
        }

        throw new \RuntimeException('Шаблон карты партнёра не найден. Выполните php scripts/build-partner-card-template.php');
    }
}
