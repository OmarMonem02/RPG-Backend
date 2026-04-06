<?php

namespace App\Services\Recovery;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SoftDeleteRecoveryService
{
    public function listDeleted(string $entity): array
    {
        $modelClass = $this->resolveModel($entity);

        return $modelClass::onlyTrashed()
            ->latest('deleted_at')
            ->get()
            ->toArray();
    }

    public function restore(string $entity, int $id): Model
    {
        $modelClass = $this->resolveModel($entity);
        $record = $modelClass::onlyTrashed()->findOrFail($id);
        $record->restore();

        return $record->fresh();
    }

    private function resolveModel(string $entity): string
    {
        $map = [
            'sales' => Sale::class,
            'tickets' => Ticket::class,
            'products' => Product::class,
            'expenses' => Expense::class,
            'customers' => Customer::class,
        ];

        $modelClass = Arr::get($map, $entity);

        if ($modelClass === null || ! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            throw ValidationException::withMessages([
                'entity' => 'Unsupported recoverable entity.',
            ]);
        }

        return $modelClass;
    }
}
