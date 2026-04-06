<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateRecurringExpensesService
{
    public function execute(string $forDate): array
    {
        return DB::transaction(function () use ($forDate): array {
            $targetDate = Carbon::parse($forDate)->startOfDay();
            $created = collect();

            Expense::query()
                ->where('is_recurring', true)
                ->whereIn('recurring_type', [
                    Expense::RECURRING_WEEKLY,
                    Expense::RECURRING_MONTHLY,
                    Expense::RECURRING_YEARLY,
                ])
                ->whereNull('generated_from_id')
                ->whereDate('expense_date', '<=', $targetDate->toDateString())
                ->orderBy('id')
                ->chunkById(100, function (Collection $expenses) use ($targetDate, $created): void {
                    foreach ($expenses as $expense) {
                        if (! $this->isDueForGeneration($expense, $targetDate)) {
                            continue;
                        }

                        $exists = Expense::query()
                            ->where('generated_from_id', $expense->id)
                            ->whereDate('source_period_date', $targetDate->toDateString())
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $created->push(Expense::query()->create([
                            'category' => $expense->category,
                            'amount' => $expense->amount,
                            'description' => $expense->description,
                            'expense_date' => $targetDate->toDateString(),
                            'paid_by' => $expense->paid_by,
                            'attachment' => $expense->attachment,
                            'is_recurring' => false,
                            'recurring_type' => Expense::RECURRING_NONE,
                            'generated_from_id' => $expense->id,
                            'source_period_date' => $targetDate->toDateString(),
                        ]));
                    }
                });

            return [
                'generated_count' => $created->count(),
                'expenses' => $created->values(),
            ];
        });
    }

    private function isDueForGeneration(Expense $expense, Carbon $targetDate): bool
    {
        $originalDate = Carbon::parse($expense->expense_date)->startOfDay();

        return match ($expense->recurring_type) {
            Expense::RECURRING_WEEKLY => $originalDate->dayOfWeek === $targetDate->dayOfWeek,
            Expense::RECURRING_MONTHLY => $originalDate->day === $targetDate->day,
            Expense::RECURRING_YEARLY => $originalDate->month === $targetDate->month && $originalDate->day === $targetDate->day,
            default => false,
        };
    }
}
