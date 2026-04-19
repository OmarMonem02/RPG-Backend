<?php

namespace App\Traits;

use App\Models\History;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsHistory
{
    public static function bootLogsHistory()
    {
        static::created(function ($model) {
            $model->logHistory('create');
        });

        static::updated(function ($model) {
            $model->logHistory('update');
        });

        static::deleted(function ($model) {
            $model->logHistory('delete');
        });
    }

    public function logHistory(string $action)
    {
        $modelId = $this->getKey();
        if ($modelId === null) {
            return;
        }

        $before = null;
        $after = null;

        if ($action === 'update') {
            $after = $this->getChanges();
            $before = array_intersect_key($this->getRawOriginal(), $after);
            
            // If no actual changes (e.g. only updated_at changed), skip logging
            if (empty($after) || (count($after) === 1 && array_key_exists('updated_at', $after))) {
                return;
            }
        } elseif ($action === 'create') {
            $after = $this->getAttributes();
        } elseif ($action === 'delete') {
            $before = $this->getRawOriginal();
        }

        try {
            History::create([
                'user_id'    => Auth::id(),
                'model_type' => get_class($this),
                'model_id'   => $modelId,
                'action'     => $action,
                'before'     => $before,
                'after'      => $after,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("History Log Error: " . $e->getMessage(), [
                'model' => get_class($this),
                'action' => $action
            ]);
        }
    }
}
