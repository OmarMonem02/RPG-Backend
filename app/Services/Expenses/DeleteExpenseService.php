<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class DeleteExpenseService
{
    public function __construct(
        private readonly UploadExpenseAttachmentService $uploadExpenseAttachmentService,
    ) {}

    public function execute(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->uploadExpenseAttachmentService->delete($expense->attachment);
            $expense->delete();
        });
    }
}
