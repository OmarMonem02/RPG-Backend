<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryIndexRequest;
use App\Http\Resources\HistoryResource;
use App\Models\History;
use App\Support\Export\ExportColumnCatalog;
use App\Support\Export\ExportColumnResolver;
use App\Support\HistoryCatalog;
use App\Support\HistoryQueryBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryController extends Controller
{
    public function __construct(
        private readonly ExportColumnResolver $columnResolver,
        private readonly ExportColumnCatalog $columnCatalog,
    ) {}

    /**
     * Get system history / audit logs.
     * Accessible by Admin only (enforced via request authorization).
     */
    public function index(HistoryIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = HistoryQueryBuilder::apply(
            History::query()->with('user'),
            $validated,
        )->latest();

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 20));
        $payload = HistoryResource::collection($paginator)->response()->getData(true);
        $payload['summary'] = HistoryQueryBuilder::summarize($validated);
        $payload['entities'] = HistoryCatalog::filterOptions();

        return response()->json($payload);
    }

    public function export(HistoryIndexRequest $request): StreamedResponse
    {
        $validated = $request->validated();
        $rows = HistoryQueryBuilder::apply(
            History::query()->with('user'),
            $validated,
        )
            ->latest()
            ->limit(5000)
            ->get();

        $columnKeys = $this->columnResolver->resolve('history', $request->query('columns'));
        $columnMap = collect($this->columnCatalog->columns('history'))->keyBy('key');

        $filename = 'system-history-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $columnKeys, $columnMap) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_map(
                fn (string $key) => $columnMap->get($key)['label'] ?? $key,
                $columnKeys,
            ));

            foreach ($rows as $history) {
                $resource = (new HistoryResource($history))->resolve();
                $values = [
                    'id' => $resource['id'],
                    'time' => $resource['created_at'],
                    'action' => $resource['action'],
                    'entity' => $resource['entity_label'],
                    'record_id' => $resource['model_id'],
                    'user' => $resource['user']['name'] ?? 'System',
                    'email' => $resource['user']['email'] ?? '',
                    'ip' => $resource['ip_address'] ?? '',
                    'summary' => implode(' | ', $resource['summary'] ?? []),
                ];

                fputcsv($handle, array_map(fn (string $key) => $values[$key] ?? '', $columnKeys));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
