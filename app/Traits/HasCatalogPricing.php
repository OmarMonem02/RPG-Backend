<?php

namespace App\Traits;

use App\Services\CatalogPricingService;

trait HasCatalogPricing
{
    protected static function bootHasCatalogPricing(): void
    {
        static::saving(function ($model): void {
            app(CatalogPricingService::class)->normalizeModel($model);
        });
    }
}
