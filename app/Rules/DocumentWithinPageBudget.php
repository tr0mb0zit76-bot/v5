<?php

namespace App\Rules;

use App\Support\DocumentPageEstimator;
use App\Support\DocumentUploadBudget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class DocumentWithinPageBudget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $maxBytes = DocumentUploadBudget::maxBytes($value);
        $size = (int) $value->getSize();

        if ($size <= $maxBytes) {
            return;
        }

        $pages = DocumentPageEstimator::estimate($value);
        $cap = max(1, (int) config('documents.max_pages_cap', 200));
        $pagesShown = min($pages, $cap);
        $perPageKb = (int) round((int) config('documents.bytes_per_page', 600 * 1024) / 1024);

        $fail(sprintf(
            'Размер файла (%.2f МБ) превышает лимит для оценённого объёма документа: до %.2f МБ (≈ %d × %d КБ/стр.).',
            $size / 1024 / 1024,
            $maxBytes / 1024 / 1024,
            $pagesShown,
            $perPageKb,
        ));
    }
}
