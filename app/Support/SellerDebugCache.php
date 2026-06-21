<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class SellerDebugCache
{
    private const KEY = 'agent_debug:sellers:last';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function store(array $payload): void
    {
        Cache::put(self::KEY, $payload, now()->addHours(6));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latest(): ?array
    {
        $payload = Cache::get(self::KEY);

        return is_array($payload) ? $payload : null;
    }
}
