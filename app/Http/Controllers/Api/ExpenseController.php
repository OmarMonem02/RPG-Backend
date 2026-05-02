<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'currency' => ['nullable', Rule::in(['EGP', 'USD'])],
            'payment_status' => ['nullable', Rule::in([Expense::STATUS_PAID, Expense::STATUS_UNPAID])],
            'category' => ['nullable', Rule::in(Expense::CATEGORIES)],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Expense::query()->latest('incurred_on')->latest('id');

        if (! empty($validated['date_from'])) {
            $query->whereDate('incurred_on', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('incurred_on', '<=', $validated['date_to']);
        }

        if (! empty($validated['currency'])) {
            $query->where('currency', $validated['currency']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 20)));
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create($request->validated());

        return response()->json($expense, 201);
    }

    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->fill($request->validated());
        $expense->save();

        return response()->json($expense);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([], 204);
    }
}
