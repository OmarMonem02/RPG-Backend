<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ImageUploadException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Services\DocumentUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentUploadService $documentUploadService)
    {
    }

    public function upload(UploadDocumentRequest $request): JsonResponse
    {
        try {
            $document = $this->documentUploadService->upload(
                $request->file('document'),
                $request->validated('folder', 'rpg-system/assets'),
            );
        } catch (ImageUploadException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($document, 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_id' => ['required', 'string'],
        ]);

        return response()->json([
            'deleted' => $this->documentUploadService->delete($validated['public_id']),
        ]);
    }
}
