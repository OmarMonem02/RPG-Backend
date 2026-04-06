<?php

namespace App\Observers;

use App\Observers\Concerns\InteractsWithAuditLogs;

class ProductObserver
{
    use InteractsWithAuditLogs;

    protected function entityType(): string
    {
        return 'product';
    }
}
