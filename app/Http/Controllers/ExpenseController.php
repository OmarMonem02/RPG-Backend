<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseFilterRequest;
use App\Http\Requests\GenerateRecurringExpensesRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\Expenses\CreateExpenseService;
use App\Services\Expenses\DeleteExpenseService;
use App\Services\Expenses\GenerateRecurringExpensesService;
use App\Services\Expenses\UpdateExpenseService;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly CreateExpenseService $createExpenseService,
        private readonly UpdateExpenseService $updateExpenseService,
        private readonly DeleteExpenseService $deleteExpenseService,
        private readonly GenerateRecurringExpensesService $generateRecurringExpensesService,
    ) {
    }

    public function index(ExpenseFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $expenses = Expense::query()
            ->when(isset($validated['category']), fn ($query) => $query->where('category', $validated['category']))
            ->when(isset($validated['paid_by']), fn ($query) => $query->where('paid_by', $validated['paid_by']))
            ->when(isset($validated['from_date']), fn ($query) => $query->whereDate('expense_date', '>=', $validated['from_date']))
            ->when(isset($validated['to_date']), fn ($query) => $query->whereDate('expense_date', '<=', $validated['to_date']))
            ->when(isset($validated['min_amount']), fn ($query) => $query->where('amount', '>=', $validated['min_amount']))
            ->when(isset($validated['max_amount']), fn ($query) => $query->where('amount', '<=', $validated['max_amount']))
            ->when(isset($validated['search']), fn ($query) => $query->where('description', 'like', '%'.$validated['search'].'%'))
            ->latest('expense_date')
            ->latest('id')
            ->get();

        return response()->json([
            'message' => 'Expenses retrieved successfully.',
            'data' => $expenses,
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->createExpenseService->execute($request->validated());

        return response()->json([
            'message' => 'Expense created successfully.',
            'data' => $expense,
        ], 201);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->updateExpenseService->execute($expense, $request->validated());

        return response()->json([
            'message' => 'Expense updated successfully.',
            'data' => $expense,
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->deleteExpenseService->execute($expense);

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }

    public function generateRecurring(GenerateRecurringExpensesRequest $request): JsonResponse
    {
        $result = $this->generateRecurringExpensesService->execute($request->validated()['for_date']);

        return response()->json([
            'message' => 'Recurring expenses generated successfully.',
            'data' => $result,
        ]);
    }
}
