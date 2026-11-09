<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;

/** Date stockée en « Y-m-d » (sans heure) pour des comparaisons identiques sous MySQL et SQLite. */
class DateOnly implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function serialize($model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->toDateString();
    }
}
