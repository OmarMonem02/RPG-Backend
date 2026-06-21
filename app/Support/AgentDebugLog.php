<?php

namespace App\Support;

final class AgentDebugLog
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function write(string $location, string $message, array $data = [], string $hypothesisId = 'H0'): void
    {
        $payload = json_encode([
            'sessionId' => '6bb7af',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'hypothesisId' => $hypothesisId,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        $paths = [
            dirname(base_path()) . DIRECTORY_SEPARATOR . 'debug-6bb7af.log',
            storage_path('logs/debug-6bb7af.log'),
        ];

        foreach ($paths as $path) {
            @file_put_contents($path, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
