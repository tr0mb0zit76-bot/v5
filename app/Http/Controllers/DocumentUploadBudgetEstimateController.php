<?php

namespace App\Http\Controllers;

use App\Support\DocumentPageEstimator;
use App\Support\DocumentUploadBudget;
use App\Support\DocumentUploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class DocumentUploadBudgetEstimateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                File::types(['pdf'])->max(DocumentUploadBudget::absoluteMaxKilobytes()),
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];

        $pages = DocumentPageEstimator::estimate($file);
        $maxBytes = DocumentUploadBudget::maxBytes($file);
        $policyMaxBytes = DocumentUploadBudget::policyMaxBytes();
        $serverMaxBytes = DocumentUploadLimits::forSharedInertia()['server_upload_max_bytes'];

        return response()->json([
            'pages' => $pages,
            'max_bytes' => $maxBytes,
            'policy_max_bytes' => $policyMaxBytes,
            'server_max_bytes' => $serverMaxBytes,
            'file_size' => (int) $file->getSize(),
            'within_budget' => (int) $file->getSize() <= $maxBytes,
        ]);
    }
}
