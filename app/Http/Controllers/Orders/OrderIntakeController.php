<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractOrderIntakeRequest;
use App\Services\Orders\OrderDocumentIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderIntakeController extends Controller
{
    public function extract(
        ExtractOrderIntakeRequest $request,
        OrderDocumentIntakeService $intakeService,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $file = $request->file('file');
        if ($file === null) {
            throw ValidationException::withMessages([
                'file' => 'Выберите файл заявки.',
            ]);
        }

        return response()->json(
            $intakeService->extractFromUpload($user, $file),
        );
    }
}
