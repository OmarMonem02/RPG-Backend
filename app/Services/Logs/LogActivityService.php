<?php

namespace App\Services\Logs;

use App\Models\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class LogActivityService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $originalData = [];

    public function rememberOriginal(Model $model): void
    {
        $this->originalData[spl_object_id($model)] = $this->sanitize($model->getRawOriginal());
    }

    public function logCreate(Model $model, string $entityType): void
    {
        $this->write(
            action: Log::ACTION_CREATE,
            entityType: $entityType,
            entityId: (int) $model->getKey(),
            oldData: null,
            newData: $this->sanitize($model->fresh()?->toArray() ?? $model->toArray()),
        );
    }

    public function logUpdate(Model $model, string $entityType): void
    {
        $key = spl_object_id($model);
        $oldData = $this->originalData[$key] ?? $this->sanitize($model->getOriginal());
        $newData = $this->sanitize($model->fresh()?->toArray() ?? $model->toArray());
        unset($this->originalData[$key]);

        if ($oldData === $newData) {
            return;
        }

        $this->write(
            action: Log::ACTION_UPDATE,
            entityType: $entityType,
            entityId: (int) $model->getKey(),
            oldData: $oldData,
            newData: $newData,
        );
    }

    public function logDelete(Model $model, string $entityType): void
    {
        $this->write(
            action: Log::ACTION_DELETE,
            entityType: $entityType,
            entityId: (int) $model->getKey(),
            oldData: $this->sanitize($model->getOriginal()),
            newData: null,
        );
    }

    public function buildDiff(Log $log): array
    {
        $oldData = $log->old_data ?? [];
        $newData = $log->new_data ?? [];
        $keys = collect(array_keys($oldData))
            ->merge(array_keys($newData))
            ->unique()
            ->values();

        return $keys->map(function (string $key) use ($oldData, $newData): array {
            $before = $oldData[$key] ?? null;
            $after = $newData[$key] ?? null;

            return [
                'field' => $key,
                'before' => $before,
                'after' => $after,
                'changed' => $before !== $after,
            ];
        })->values()->all();
    }

    private function write(string $action, string $entityType, int $entityId, ?array $oldData, ?array $newData): void
    {
        Log::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }

    private function sanitize(array $data): array
    {
        return Arr::except($data, [
            'password',
            'remember_token',
        ]);
    }
}
