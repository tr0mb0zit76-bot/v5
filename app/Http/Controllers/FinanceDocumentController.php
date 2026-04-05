<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinanceDocumentRequest;
use App\Http\Requests\UpdateFinanceDocumentRequest;
use App\Models\FinanceDocument;
use Illuminate\Http\JsonResponse;

class FinanceDocumentController extends Controller
{
    public function store(StoreFinanceDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'draft';
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        $document = FinanceDocument::create($data);

        return response()->json([
            'document' => $document,
        ], 201);
    }

    public function update(UpdateFinanceDocumentRequest $request, FinanceDocument $financeDocument): JsonResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        $financeDocument->fill($data);
        $financeDocument->save();

        return response()->json([
            'document' => $financeDocument,
        ]);
    }
}
