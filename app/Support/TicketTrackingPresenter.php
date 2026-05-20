<?php

namespace App\Support;

use Illuminate\Support\Collection;

class TicketTrackingPresenter
{
    /**
     * @return array{
     *     name: string,
     *     tagline: string,
     *     logo_url: string|null,
     *     auto_refresh_minutes: int
     * }
     */
    public static function shop(): array
    {
        $logoUrl = trim((string) config('shop.logo_url'));

        return [
            'name' => (string) config('shop.name'),
            'tagline' => (string) config('shop.tagline'),
            'logo_url' => $logoUrl !== '' ? $logoUrl : null,
            'auto_refresh_minutes' => max(0, (int) config('shop.tracking_auto_refresh_minutes')),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Received',
            'in_progress' => 'In progress',
            'completed' => 'Ready for pickup',
            'closed' => 'Closed',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    public static function buildTimeline(?string $status): array
    {
        $steps = [
            ['key' => 'pending', 'label' => 'Received'],
            ['key' => 'in_progress', 'label' => 'In progress'],
            ['key' => 'completed', 'label' => 'Completed'],
        ];

        $order = ['pending' => 0, 'in_progress' => 1, 'completed' => 2];
        $current = $order[$status ?? 'pending'] ?? 0;

        return array_map(function (array $step) use ($order, $current) {
            $stepOrder = $order[$step['key']] ?? 0;
            $state = 'upcoming';

            if ($stepOrder < $current) {
                $state = 'done';
            } elseif ($stepOrder === $current) {
                $state = 'current';
            }

            return [...$step, 'state' => $state];
        }, $steps);
    }

    /**
     * @param  Collection<int, mixed>  $tasks
     * @return array{
     *     timeline: list<array{key: string, label: string, state: string}>,
     *     tasks_completed: int,
     *     tasks_total: int,
     *     tasks_percent: int,
     *     current_step: int,
     *     total_steps: int
     * }
     */
    public static function buildProgress(?string $status, Collection $tasks): array
    {
        $timeline = self::buildTimeline($status);
        $tasksCompleted = $tasks->where('status', 'completed')->count();
        $tasksTotal = $tasks->count();
        $tasksPercent = $tasksTotal > 0
            ? (int) round(($tasksCompleted / $tasksTotal) * 100)
            : 0;

        $currentStep = 1;
        foreach ($timeline as $index => $step) {
            if ($step['state'] === 'current') {
                $currentStep = $index + 1;
                break;
            }
            if ($step['state'] === 'done' && $index === count($timeline) - 1) {
                $currentStep = count($timeline);
            }
        }

        return [
            'timeline' => $timeline,
            'tasks_completed' => $tasksCompleted,
            'tasks_total' => $tasksTotal,
            'tasks_percent' => $tasksPercent,
            'current_step' => $currentStep,
            'total_steps' => count($timeline),
        ];
    }
}
