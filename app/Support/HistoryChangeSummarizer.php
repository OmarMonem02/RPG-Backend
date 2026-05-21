<?php

namespace App\Support;

class HistoryChangeSummarizer
{
    private const IGNORED_KEYS = [
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
        'password',
        'password_confirmation',
    ];

    public static function summarize(?string $action, mixed $before, mixed $after, int $limit = 8): array
    {
        $beforeArray = self::normalizePayload($before);
        $afterArray = self::normalizePayload($after);

        if ($action === 'create') {
            return self::limit(self::summarizeCreate($afterArray), $limit);
        }

        if ($action === 'delete') {
            return self::limit(self::summarizeDelete($beforeArray), $limit);
        }

        return self::limit(self::summarizeUpdate($beforeArray, $afterArray), $limit);
    }

    private static function summarizeCreate(array $after): array
    {
        $lines = [];
        foreach ($after as $key => $value) {
            if (self::shouldIgnoreKey($key)) {
                continue;
            }
            $lines[] = self::formatField($key) . ': ' . self::formatValue($value);
        }

        return $lines ?: ['Record created'];
    }

    private static function summarizeDelete(array $before): array
    {
        if ($before === []) {
            return ['Record deleted'];
        }

        $lines = ['Record deleted'];
        $preview = array_slice(
            array_filter(
                $before,
                fn ($key) => ! self::shouldIgnoreKey((string) $key),
                ARRAY_FILTER_USE_KEY,
            ),
            0,
            3,
            true,
        );

        foreach ($preview as $key => $value) {
            $lines[] = self::formatField((string) $key) . ': ' . self::formatValue($value);
        }

        return $lines;
    }

    private static function summarizeUpdate(array $before, array $after): array
    {
        $lines = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            if (self::shouldIgnoreKey($key)) {
                continue;
            }

            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $lines[] = self::formatField($key) . ': '
                . self::formatValue($oldValue)
                . ' → '
                . self::formatValue($newValue);
        }

        return $lines ?: ['Record updated'];
    }

    private static function normalizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }

    private static function shouldIgnoreKey(string $key): bool
    {
        return in_array($key, self::IGNORED_KEYS, true);
    }

    private static function formatField(string $key): string
    {
        return HistoryFieldLabels::label($key);
    }

    /**
     * @return array<int, array{field: string, label: string, before: string, after: string}>
     */
    public static function diffEntries(?string $action, mixed $before, mixed $after, int $limit = 24): array
    {
        $beforeArray = self::normalizePayload($before);
        $afterArray = self::normalizePayload($after);
        $entries = [];

        if ($action === 'create') {
            foreach ($afterArray as $key => $value) {
                if (self::shouldIgnoreKey((string) $key)) {
                    continue;
                }
                $entries[] = [
                    'field' => (string) $key,
                    'label' => self::formatField((string) $key),
                    'before' => '—',
                    'after' => self::formatValue($value),
                ];
            }

            return self::limitDiff($entries, $limit);
        }

        if ($action === 'delete') {
            foreach ($beforeArray as $key => $value) {
                if (self::shouldIgnoreKey((string) $key)) {
                    continue;
                }
                $entries[] = [
                    'field' => (string) $key,
                    'label' => self::formatField((string) $key),
                    'before' => self::formatValue($value),
                    'after' => '—',
                ];
            }

            return self::limitDiff($entries, $limit);
        }

        $keys = array_unique(array_merge(array_keys($beforeArray), array_keys($afterArray)));
        foreach ($keys as $key) {
            if (self::shouldIgnoreKey((string) $key)) {
                continue;
            }

            $oldValue = $beforeArray[$key] ?? null;
            $newValue = $afterArray[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $entries[] = [
                'field' => (string) $key,
                'label' => self::formatField((string) $key),
                'before' => self::formatValue($oldValue),
                'after' => self::formatValue($newValue),
            ];
        }

        return self::limitDiff($entries, $limit);
    }

    /**
     * @param  array<int, array{field: string, label: string, before: string, after: string}>  $entries
     * @return array<int, array{field: string, label: string, before: string, after: string}>
     */
    private static function limitDiff(array $entries, int $limit): array
    {
        if (count($entries) <= $limit) {
            return $entries;
        }

        return array_slice($entries, 0, $limit);
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

            return mb_strlen((string) $encoded) > 80
                ? mb_substr((string) $encoded, 0, 77) . '...'
                : (string) $encoded;
        }

        $text = trim((string) $value);

        return $text === '' ? '—' : $text;
    }

    private static function limit(array $lines, int $limit): array
    {
        if (count($lines) <= $limit) {
            return $lines;
        }

        $remaining = count($lines) - $limit;

        return [
            ...array_slice($lines, 0, $limit),
            "+{$remaining} more change(s)",
        ];
    }
}
