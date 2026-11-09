<?php

namespace App\Services;

/** Estimations indicatives, jamais stockées (règle 9). */
class CalorieEstimator
{
    /** ≈ 1 kcal × kg × km. */
    public static function run(float $weightKg, float $km): float
    {
        return $weightKg * $km;
    }

    /** MET ≈ 4,5. */
    public static function strength(float $weightKg, int $seconds): float
    {
        return 4.5 * $weightKg * $seconds / 3600;
    }
}
