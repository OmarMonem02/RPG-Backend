<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadExpenseAttachmentService
{
    public function upload(UploadedFile $file): string
    {
        return $file->store('expenses', 'public');
    }

    public function delete(?string $path): void
    {
        if (
            $path !== null &&
            $path !== '' &&
            Expense::query()->where('attachment', $path)->count() <= 1 &&
            Storage::disk('public')->exists($path)
        ) {
            Storage::disk('public')->delete($path);
        }
    }
}
