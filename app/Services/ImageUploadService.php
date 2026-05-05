<?php

namespace App\Services;

use App\Exceptions\ImageUploadException;
use Illuminate\Http\UploadedFile;
use Throwable;

class ImageUploadService
{
    public function upload(UploadedFile $file, string $folder = 'rpg-system'): array
    {
        try {
            $result = cloudinary()->upload($file->getRealPath(), [
                'folder' => $folder,
                'transformation' => [
                    'width' => 1200,
                    'crop' => 'limit',
                ],
            ]);
        } catch (Throwable $exception) {
            throw new ImageUploadException('Unable to upload image.', previous: $exception);
        }

        return [
            'url' => $result->getSecurePath(),
            'public_id' => $result->getPublicId(),
        ];
    }

    public function delete(string $publicId): bool
    {
        try {
            $result = cloudinary()->uploadApi()->destroy($publicId);
        } catch (Throwable) {
            return false;
        }

        return ($result['result'] ?? null) === 'ok';
    }
}
