<?php

namespace App\Observers\Concerns;

use App\Services\Logs\LogActivityService;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithAuditLogs
{
    abstract protected function entityType(): string;

    protected function activityService(): LogActivityService
    {
        return app(LogActivityService::class);
    }

    public function created(Model $model): void
    {
        $this->activityService()->logCreate($model, $this->entityType());
    }

    public function updating(Model $model): void
    {
        $this->activityService()->rememberOriginal($model);
    }

    public function updated(Model $model): void
    {
        $this->activityService()->logUpdate($model, $this->entityType());
    }

    public function deleted(Model $model): void
    {
        $this->activityService()->logDelete($model, $this->entityType());
    }
}
