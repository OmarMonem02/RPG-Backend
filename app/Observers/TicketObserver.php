<?php

namespace App\Observers;

use App\Observers\Concerns\InteractsWithAuditLogs;

class TicketObserver
{
    use InteractsWithAuditLogs;

    protected function entityType(): string
    {
        return 'ticket';
    }
}
