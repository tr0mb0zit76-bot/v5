<?php

namespace App\Http\Controllers;

use App\Services\Documents\OcrServiceClient;
use App\Support\DocumentUploadBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class DocumentOptimizeController extends Controller
{
    public function __invoke(Request $request, OcrServiceClient $ocr): JsonResponse
    {
        if (! $ocr->isOptimizeEnabled()) {
            return response()->json([
                'message' => 'Оптимизация PDF на сервере отключена.',
            ], 503);
        }

        $validated = $request->validate([
            'file' => [
                'required',
                File::types(['pdf'])->max(DocumentUploadBudget::absoluteMaxKilobytes()),
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];

        $result = $ocr->optimizePdfUpload($file);

        if ($result === null) {
            return response()->json([
                'message' => 'Не удалось оптимизировать PDF. Подготовьте файл вручную или повторите позже.',
            ], 422);
        }

        return response()->json($result);
    }
}
