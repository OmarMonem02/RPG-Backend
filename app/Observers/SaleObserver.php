<?php

namespace App\Observers;

use App\Observers\Concerns\InteractsWithAuditLogs;

class SaleObserver
{
    use InteractsWithAuditLogs;

    protected function entityType(): string
    {
        return 'sale';
    }
}
