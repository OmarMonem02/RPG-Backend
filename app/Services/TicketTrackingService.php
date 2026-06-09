<?php

namespace App\Services;

use App\Models\Ticket;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TicketTrackingService
{
    public const SESSION_TTL_HOURS = 24;

    public const SESSION_HEADER = 'X-Tracking-Session';

    public function ensurePublicToken(Ticket $ticket): Ticket
    {
        if ($ticket->public_token) {
            return $ticket;
        }

        $ticket->update(['public_token' => (string) Str::uuid()]);

        return $ticket->fresh();
    }

    public function regeneratePublicToken(Ticket $ticket): Ticket
    {
        $ticket->update(['public_token' => (string) Str::uuid()]);

        return $ticket->fresh();
    }

    public function buildTrackingUrl(Ticket $ticket): string
    {
        $base = rtrim((string) config('services.frontend.public_url'), '/');
        $token = $ticket->public_token;

        return "{$base}/track/{$token}";
    }

    public function findByPublicToken(string $token): ?Ticket
    {
        return Ticket::query()
            ->where('public_token', $token)
            ->with([
                'customer',
                'customerBike.bikeBlueprint.brand',
                'tasks.items.sparePart',
                'tasks.items.maintenanceService',
                'tasks.items.product',
            ])
            ->first();
    }

    public function verifyPhone(Ticket $ticket, string $phone): bool
    {
        $customerPhone = $ticket->customer?->phone;

        return PhoneNormalizer::matches($phone, $customerPhone);
    }

    public function createSession(Ticket $ticket): string
    {
        $sessionId = Str::random(64);

        Cache::put(
            $this->sessionCacheKey($sessionId),
            [
                'ticket_id' => $ticket->id,
                'public_token' => $ticket->public_token,
            ],
            now()->addHours(self::SESSION_TTL_HOURS)
        );

        return $sessionId;
    }

    public function resolveSession(string $sessionId, string $publicToken): ?Ticket
    {
        $payload = Cache::get($this->sessionCacheKey($sessionId));

        if (! is_array($payload)) {
            return null;
        }

        if (($payload['public_token'] ?? null) !== $publicToken) {
            return null;
        }

        return Ticket::query()
            ->where('id', $payload['ticket_id'] ?? 0)
            ->where('public_token', $publicToken)
            ->with([
                'customer',
                'customerBike.bikeBlueprint.brand',
                'tasks.items.sparePart',
                'tasks.items.maintenanceService',
                'tasks.items.product',
            ])
            ->first();
    }

    public function recordLinkSent(Ticket $ticket): void
    {
        $ticket->update([
            'tracking_link_sent_at' => now(),
            'tracking_link_send_count' => ($ticket->tracking_link_send_count ?? 0) + 1,
        ]);
    }

    private function sessionCacheKey(string $sessionId): string
    {
        return "tracking_session:{$sessionId}";
    }
}
