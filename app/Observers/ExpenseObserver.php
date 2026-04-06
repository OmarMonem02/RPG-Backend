<?php

namespace App\Observers;

use App\Observers\Concerns\InteractsWithAuditLogs;

class ExpenseObserver
{
    use InteractsWithAuditLogs;

    protected function entityType(): string
    {
        return 'expense';
    }
}
