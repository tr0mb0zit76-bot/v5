<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class ContractorDocumentSyncService
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public function sync(Contractor $contractor, array $documents, ?int $userId): void
    {
        if (! Schema::hasTable('contractor_documents')) {
            return;
        }

        $hasFileColumns = Schema::hasColumn('contractor_documents', 'file_path');
        $existingById = $contractor->documents()->get()->keyBy('id');
        $keptIds = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $title = trim((string) ($document['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $id = isset($document['id']) ? (int) $document['id'] : null;
            $file = $document['file'] ?? null;
            $attributes = [
                'type' => $this->nullIfBlank($document['type'] ?? null),
                'title' => $title,
                'number' => $this->nullIfBlank($document['number'] ?? null),
                'document_date' => $document['document_date'] ?? null,
                'status' => $this->nullIfBlank($document['status'] ?? null),
                'notes' => $this->nullIfBlank($document['notes'] ?? null),
            ];

            if ($hasFileColumns && $file instanceof UploadedFile) {
                $stored = $this->documentStorage->storeContractorUpload($file, $contractor->id);
                $attributes['original_name'] = $stored['original_name'];
                $attributes['file_path'] = $stored['file_path'];
                $attributes['file_size'] = $stored['file_size'];
                $attributes['mime_type'] = $stored['mime_type'];
                $attributes['storage_driver'] = $stored['storage_driver'];
            }

            if ($id !== null && $existingById->has($id)) {
                /** @var ContractorDocument $model */
                $model = $existingById->get($id);

                if ($hasFileColumns && $file instanceof UploadedFile && filled($model->file_path)) {
                    $this->documentStorage->delete($model->file_path, $model->storage_driver);
                }

                $model->update($attributes);
                $keptIds[] = $id;

                continue;
            }

            if ($hasFileColumns && ! ($file instanceof UploadedFile)) {
                continue;
            }

            $contractor->documents()->create([
                ...$attributes,
                'created_by' => $userId,
            ]);
        }

        $idsToDelete = $existingById->keys()
            ->map(fn (mixed $key): int => (int) $key)
            ->diff($keptIds)
            ->values();

        if ($idsToDelete->isEmpty()) {
            return;
        }

        $contractor->documents()
            ->whereIn('id', $idsToDelete->all())
            ->get()
            ->each(function (ContractorDocument $document) use ($hasFileColumns): void {
                if ($hasFileColumns && filled($document->file_path)) {
                    $this->documentStorage->delete($document->file_path, $document->storage_driver);
                }

                $document->delete();
            });
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
