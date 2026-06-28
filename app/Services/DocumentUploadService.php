<?php

namespace App\Services;

use App\Exceptions\ImageUploadException;
use Illuminate\Http\UploadedFile;
use Throwable;

class DocumentUploadService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function upload(UploadedFile $file, string $folder = 'rpg-system/assets'): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new ImageUploadException('Document exceeds the 10MB upload limit.');
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new ImageUploadException('Only PDF, JPEG, PNG, and WebP documents are allowed.');
        }

        try {
            // PDFs are uploaded as image assets so page previews work on all plans.
            $resourceType = 'image';
            $options = [
                'folder' => $folder,
                'resource_type' => $resourceType,
                'transformation' => [
                    'width' => 1600,
                    'crop' => 'limit',
                ],
            ];

            $result = cloudinary()->upload($file->getRealPath(), $options);
        } catch (Throwable $exception) {
            throw new ImageUploadException('Unable to upload document.', previous: $exception);
        }

        return [
            'url' => $result->getSecurePath(),
            'public_id' => $result->getPublicId(),
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
        ];
    }

    public function delete(string $publicId): bool
    {
        try {
            $result = cloudinary()->uploadApi()->destroy($publicId, [
                'resource_type' => 'auto',
            ]);
        } catch (Throwable) {
            return false;
        }

        return in_array($result['result'] ?? null, ['ok', 'not found'], true);
    }
}
