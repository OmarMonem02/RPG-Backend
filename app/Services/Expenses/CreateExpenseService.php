<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class CreateExpenseService
{
    public function __construct(
        private readonly UploadExpenseAttachmentService $uploadExpenseAttachmentService,
    ) {}

    public function execute(array $data): Expense
    {
        return DB::transaction(function () use ($data): Expense {
            $attachmentPath = isset($data['attachment'])
                ? $this->uploadExpenseAttachmentService->upload($data['attachment'])
                : null;

            return Expense::query()->create([
                'category' => $data['category'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'expense_date' => $data['expense_date'],
                'paid_by' => $data['paid_by'],
                'attachment' => $attachmentPath,
                'is_recurring' => (bool) ($data['is_recurring'] ?? false),
                'recurring_type' => $data['is_recurring'] ?? false
                    ? ($data['recurring_type'] ?? Expense::RECURRING_MONTHLY)
                    : Expense::RECURRING_NONE,
                'generated_from_id' => null,
                'source_period_date' => null,
            ]);
        });
    }
}
