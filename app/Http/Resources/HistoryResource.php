<?php

namespace App\Http\Resources;

use App\Models\History;
use App\Support\HistoryCatalog;
use App\Support\HistoryChangeSummarizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin History */
class HistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resolved = HistoryCatalog::resolveFromModelType($this->model_type);
        $entityType = $resolved['entity_type'] ?? null;
        $entityLabel = $resolved['entity_label'] ?? 'Record';
        $modelId = (int) $this->model_id;
        $changes = HistoryChangeSummarizer::diffEntries(
            $this->action,
            $this->before,
            $this->after,
        );

        return [
            'id' => $this->id,
            'action' => $this->action,
            'created_at' => $this->created_at?->toIso8601String(),
            'ip_address' => $this->ip_address,
            'entity_type' => $entityType,
            'entity_label' => $entityLabel,
            'model_type' => $this->model_type,
            'model_id' => $modelId,
            'entity_path' => HistoryCatalog::entityPath($entityType, $modelId),
            'summary' => HistoryChangeSummarizer::summarize(
                $this->action,
                $this->before,
                $this->after,
            ),
            'changes' => $changes,
            'changes_count' => count($changes),
            'before' => $this->before,
            'after' => $this->after,
            'user' => $this->whenLoaded('user', function () {
                if (! $this->user) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
