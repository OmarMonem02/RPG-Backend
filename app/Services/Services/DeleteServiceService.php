<?php

namespace App\Services\Services;

use App\Models\Service;
use Illuminate\Validation\ValidationException;

class DeleteServiceService
{
    public function execute(Service $service): void
    {
        if ($service->ticketItems()->exists()) {
            throw ValidationException::withMessages([
                'service' => 'Service cannot be deleted while linked to tickets.',
            ]);
        }

        $service->delete();
    }
}
