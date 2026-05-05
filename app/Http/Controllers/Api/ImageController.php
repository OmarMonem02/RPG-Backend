<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ImageUploadException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function upload(UploadImageRequest $request): JsonResponse
    {
        try {
            $image = $this->imageUploadService->upload(
                $request->file('image'),
                $request->validated('folder', 'rpg-system/general')
            );
        } catch (ImageUploadException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json($image, 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_id' => ['required', 'string'],
        ]);

        return response()->json([
            'deleted' => $this->imageUploadService->delete($validated['public_id']),
        ]);
    }
}
