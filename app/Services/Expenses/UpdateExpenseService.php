<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class UpdateExpenseService
{
    public function __construct(
        private readonly UploadExpenseAttachmentService $uploadExpenseAttachmentService,
    ) {}

    public function execute(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if (! empty($data['remove_attachment'])) {
                $this->uploadExpenseAttachmentService->delete($expense->attachment);
                $expense->attachment = null;
            }

            if (isset($data['attachment'])) {
                $this->uploadExpenseAttachmentService->delete($expense->attachment);
                $expense->attachment = $this->uploadExpenseAttachmentService->upload($data['attachment']);
            }

            $expense->fill([
                'category' => $data['category'] ?? $expense->category,
                'amount' => $data['amount'] ?? $expense->amount,
                'description' => array_key_exists('description', $data) ? $data['description'] : $expense->description,
                'expense_date' => $data['expense_date'] ?? $expense->expense_date,
                'paid_by' => $data['paid_by'] ?? $expense->paid_by,
                'is_recurring' => $data['is_recurring'] ?? $expense->is_recurring,
                'recurring_type' => ($data['is_recurring'] ?? $expense->is_recurring)
                    ? ($data['recurring_type'] ?? $expense->recurring_type)
                    : Expense::RECURRING_NONE,
            ]);

            $expense->save();

            return $expense->fresh();
        });
    }
}
