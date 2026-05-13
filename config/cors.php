<?php

/**
 * Parse a comma-separated CORS list from the environment.
 * Empty or missing values fall back to $default so credentials + CORS never end up with no origins.
 *
 * @return list<string>
 */
$corsCsvList = static function (string $key, string $default): array {
    $raw = env($key);

    if (! is_string($raw) || trim($raw) === '') {
        $raw = $default;
    }

    $items = array_map(trim(...), explode(',', $raw));

    return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
};

/**
 * Parse comma-separated origin regex patterns. Missing key uses $default; explicit empty string means none.
 * Drops patterns that are not valid PCRE so preg_match never errors at runtime.
 *
 * @return list<string>
 */
$corsPatternList = static function (string $key, string $default): array {
    $raw = env($key);

    if ($raw === null || ! is_string($raw)) {
        $raw = $default;
    }

    if (trim($raw) === '') {
        return [];
    }

    $items = array_map(trim(...), explode(',', $raw));

    return array_values(array_filter(
        $items,
        static function (string $pattern): bool {
            if ($pattern === '') {
                return false;
            }

            return @preg_match($pattern, '') !== false;
        }
    ));
};

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $corsCsvList(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000,http://127.0.0.1:3001,https://rpg-erp-system.vercel.app'
    ),

    // Regex patterns for origins. Defaults to matching the production Vercel host (literal dots).
    // Preview URLs can be added via CORS_ALLOWED_ORIGINS or extra patterns in .env.
    'allowed_origins_patterns' => $corsPatternList(
        'CORS_ALLOWED_ORIGINS_PATTERNS',
        '#^https://rpg-erp-system\.vercel\.app$#'
    ),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
