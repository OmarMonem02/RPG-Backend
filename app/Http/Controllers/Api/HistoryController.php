<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryIndexRequest;
use App\Http\Resources\HistoryResource;
use App\Models\History;
use App\Support\HistoryCatalog;
use App\Support\HistoryQueryBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryController extends Controller
{
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

        $filename = 'system-history-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Time',
                'Action',
                'Entity',
                'Record ID',
                'User',
                'Email',
                'IP',
                'Summary',
            ]);

            foreach ($rows as $history) {
                $resource = (new HistoryResource($history))->resolve();
                fputcsv($handle, [
                    $resource['id'],
                    $resource['created_at'],
                    $resource['action'],
                    $resource['entity_label'],
                    $resource['model_id'],
                    $resource['user']['name'] ?? 'System',
                    $resource['user']['email'] ?? '',
                    $resource['ip_address'] ?? '',
                    implode(' | ', $resource['summary'] ?? []),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
