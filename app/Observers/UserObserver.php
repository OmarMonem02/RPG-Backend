<?php

namespace App\Observers;

use App\Observers\Concerns\InteractsWithAuditLogs;

class UserObserver
{
    use InteractsWithAuditLogs;

    protected function entityType(): string
    {
        return 'user';
    }
}
