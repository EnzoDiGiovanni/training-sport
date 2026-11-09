<?php

namespace App\Services;

use App\Models\RunSegment;
use App\Models\Workout;

/**
 * Calculs de course. Un déroulé est un arbre de segments :
 * ['kind','label','duration_min','speed_min_kmh','speed_max_kmh','incline_pct'] ou
 * ['kind' => 'repeat', 'repeat_count' => n, 'children' => [...]].
 */
class RunMetrics
{
    /** Vitesse moyenne en km/h (distance / durée), null si une des valeurs manque. */
    public static function averageSpeed(?float $km, int $seconds): ?float
    {
        return $km && $seconds > 0 ? $km / ($seconds / 3600) : null;
    }

    /** « 30:00 », « 1:05:30 » → secondes ; null si le format est invalide. */
    public static function parseClock(?string $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^(?:(\d+):)?([0-5]?\d):([0-5]\d)$/', trim($value), $m)) {
            return null;
        }

        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    }

    /** @return list<array<string,mixed>> */
    public static function tree(Workout $workout): array
    {
        $workout->loadMissing('segments.children');

        return $workout->segments->map(fn (RunSegment $s) => self::segment($s))->all();
    }

    /** @return array<string,mixed> */
    public static function segment(RunSegment $s): array
    {
        if ($s->kind->value === 'repeat') {
            return ['kind' => 'repeat', 'repeat_count' => $s->repeat_count,
                'children' => $s->children->map(fn ($c) => self::segment($c))->all()];
        }

        return ['kind' => $s->kind->value, 'label' => $s->label, 'duration_min' => $s->duration_min,
            'speed_min_kmh' => $s->speed_min_kmh, 'speed_max_kmh' => $s->speed_max_kmh, 'incline_pct' => $s->incline_pct ?? 0];
    }

    /** Étapes à plat, les blocs répétés étant déroulés. @return list<array<string,mixed>> */
    public static function flatten(array $tree): array
    {
        $out = [];
        foreach ($tree as $s) {
            if (($s['kind'] ?? null) === 'repeat') {
                for ($i = 0; $i < (int) $s['repeat_count']; $i++) {
                    array_push($out, ...self::flatten($s['children'] ?? []));
                }
            } else {
                $out[] = $s;
            }
        }

        return $out;
    }

    public static function plannedMinutes(array $tree): float
    {
        return array_sum(array_map(fn ($s) => (float) $s['duration_min'], self::flatten($tree)));
    }

    public static function estimatedKm(array $tree): float
    {
        return array_sum(array_map(
            fn ($s) => $s['duration_min'] / 60 * (($s['speed_min_kmh'] + $s['speed_max_kmh']) / 2),
            self::flatten($tree),
        ));
    }

    public static function maxSpeed(array $tree): float
    {
        return max([0, ...array_map(fn ($s) => (float) $s['speed_max_kmh'], self::flatten($tree))]);
    }

    public static function plannedCycles(array $tree): ?int
    {
        foreach ($tree as $s) {
            if ($s['kind'] === 'repeat') {
                return (int) $s['repeat_count'];
            }
        }

        return null;
    }
}
