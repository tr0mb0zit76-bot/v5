<?php

namespace App\Console\Commands;

use App\Models\OrderDocument;
use App\Services\DocumentStorageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('documents:migrate-order-documents-to-nextcloud {--dry-run : Только показать, что будет перенесено} {--limit=0 : Ограничить количество документов для прогона}')]
#[Description('Переносит файлы order_documents с local-диска в Nextcloud и помечает storage_driver в metadata.')]
class MigrateOrderDocumentsToNextcloudCommand extends Command
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('order_documents')) {
            $this->warn('Таблица order_documents не найдена.');

            return self::SUCCESS;
        }

        if (! Schema::hasColumn('order_documents', 'metadata')
            || ! Schema::hasColumn('order_documents', 'file_path')
            || ! Schema::hasColumn('order_documents', 'generated_pdf_path')) {
            $this->warn('В таблице order_documents не хватает нужных колонок.');

            return self::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $limit = max((int) $this->option('limit'), 0);

        $query = OrderDocument::query()
            ->where(function ($q): void {
                $q->whereNotNull('file_path')
                    ->orWhereNotNull('generated_pdf_path');
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $documents = $query->get();

        $processed = 0;
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($documents as $document) {
            $processed++;
            $metadata = is_array($document->metadata) ? $document->metadata : [];

            $draftStatus = $this->migrateDraftPath($document, $metadata, $isDryRun);
            $finalStatus = $this->migrateFinalPdfPath($document, $metadata, $isDryRun);

            if ($draftStatus['failed'] || $finalStatus['failed']) {
                $failed++;
                continue;
            }

            if ($draftStatus['migrated'] || $finalStatus['migrated']) {
                $migrated++;
            } else {
                $skipped++;
            }

            if (! $isDryRun && ($draftStatus['migrated'] || $finalStatus['migrated'])) {
                $document->metadata = $metadata;
                $document->saveQuietly();
            }
        }

        $this->newLine();
        $this->info("Проверено документов: {$processed}");
        $this->info("Перенесено: {$migrated}");
        $this->line("Пропущено: {$skipped}");
        $this->line("Ошибок: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{migrated: bool, failed: bool}
     */
    private function migrateDraftPath(OrderDocument $document, array &$metadata, bool $dryRun): array
    {
        if (blank($document->file_path)) {
            return ['migrated' => false, 'failed' => false];
        }

        $currentDriver = (string) data_get($metadata, 'storage_driver', DocumentStorageService::DRIVER_LOCAL);
        if ($currentDriver === DocumentStorageService::DRIVER_NEXTCLOUD) {
            return ['migrated' => false, 'failed' => false];
        }

        $path = (string) $document->file_path;

        if ($dryRun) {
            $this->line("DRY-RUN: draft #{$document->id} {$path}");
            $metadata['storage_driver'] = DocumentStorageService::DRIVER_NEXTCLOUD;

            return ['migrated' => true, 'failed' => false];
        }

        try {
            $contents = $this->documentStorage->get($path, DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->put($path, $contents, DocumentStorageService::DRIVER_NEXTCLOUD);
            $this->documentStorage->delete($path, DocumentStorageService::DRIVER_LOCAL);
            $metadata['storage_driver'] = DocumentStorageService::DRIVER_NEXTCLOUD;
        } catch (\Throwable $e) {
            $this->error("Ошибка draft #{$document->id}: {$e->getMessage()}");

            return ['migrated' => false, 'failed' => true];
        }

        $this->info("Перенесён draft #{$document->id}");

        return ['migrated' => true, 'failed' => false];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{migrated: bool, failed: bool}
     */
    private function migrateFinalPdfPath(OrderDocument $document, array &$metadata, bool $dryRun): array
    {
        if (blank($document->generated_pdf_path)) {
            return ['migrated' => false, 'failed' => false];
        }

        $currentDriver = (string) data_get($metadata, 'generated_pdf_storage_driver', DocumentStorageService::DRIVER_LOCAL);
        if ($currentDriver === DocumentStorageService::DRIVER_NEXTCLOUD) {
            return ['migrated' => false, 'failed' => false];
        }

        $path = (string) $document->generated_pdf_path;

        if ($dryRun) {
            $this->line("DRY-RUN: final #{$document->id} {$path}");
            $metadata['generated_pdf_storage_driver'] = DocumentStorageService::DRIVER_NEXTCLOUD;

            return ['migrated' => true, 'failed' => false];
        }

        try {
            $contents = $this->documentStorage->get($path, DocumentStorageService::DRIVER_LOCAL);
            $this->documentStorage->put($path, $contents, DocumentStorageService::DRIVER_NEXTCLOUD);
            $this->documentStorage->delete($path, DocumentStorageService::DRIVER_LOCAL);
            $metadata['generated_pdf_storage_driver'] = DocumentStorageService::DRIVER_NEXTCLOUD;
        } catch (\Throwable $e) {
            $this->error("Ошибка final #{$document->id}: {$e->getMessage()}");

            return ['migrated' => false, 'failed' => true];
        }

        $this->info("Перенесён final #{$document->id}");

        return ['migrated' => true, 'failed' => false];
    }
}
