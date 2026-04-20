<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Лимит размера загружаемых документов от числа страниц
    |--------------------------------------------------------------------------
    |
    | Максимальный размер файла ≈ (оценка страниц) × bytes_per_page.
    | Для PHP post_max_size на проде задайте не меньше:
    |   ceil(max_pages_cap × bytes_per_page / 1024 / 1024) МБ + запас.
    |
    */
    'bytes_per_page' => max(1024, (int) env('DOCUMENT_BYTES_PER_PAGE', 600 * 1024)),

    /** Верхняя граница оценки страниц (анти-ошибка парсера / злоупотребление). */
    'max_pages_cap' => max(1, (int) env('DOCUMENT_MAX_PAGES_CAP', 200)),

    /**
     * Если тип файла не позволяет надёжно посчитать страницы (.doc, неизвестный),
     * используется эта оценка (минимум 1).
     */
    'fallback_pages_unknown' => max(1, (int) env('DOCUMENT_FALLBACK_PAGES_UNKNOWN', 12)),

    /**
     * Для растровых вложений (jpg/png/webp) «страниц» нет — берётся этот множитель к бюджету.
     * Например 18 ≈ 18 × 600 КиБ ≈ 10,5 МиБ (близко к старым лимитам ~10 МБ).
     */
    'image_placeholder_pages' => max(1, (int) env('DOCUMENT_IMAGE_PLACEHOLDER_PAGES', 18)),

    /** Сколько байт читать с начала/конца PDF при оценке страниц (большие файлы). */
    'pdf_head_scan_bytes' => max(256_000, (int) env('DOCUMENT_PDF_HEAD_SCAN_BYTES', 4 * 1024 * 1024)),
    'pdf_tail_scan_bytes' => max(256_000, (int) env('DOCUMENT_PDF_TAIL_SCAN_BYTES', 4 * 1024 * 1024)),
];
