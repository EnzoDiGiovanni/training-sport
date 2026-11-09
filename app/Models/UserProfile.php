<?php

namespace App\Models;

use App\Enums\LoadType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'target_weight_kg' => 'float', 'run_level_kmh' => 'float', 'treadmill_max_kmh' => 'float',
            'treadmill_incline_max' => 'float', 'dumbbell_max_kg' => 'float', 'weight_step_kg' => 'float',
            'weekly_km_goal' => 'float', 'dumbbells_combinable' => 'boolean', 'has_bench' => 'boolean',
            'bench_incline' => 'boolean', 'goals' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Charge max utilisable pour un type de charge (règle 7) ; null = pas de limite (poids du corps). */
    public function maxLoadFor(LoadType $type): ?float
    {
        return match ($type) {
            LoadType::TwoDumbbells => $this->dumbbell_max_kg,
            LoadType::OneDumbbell => $this->dumbbells_combinable ? $this->dumbbell_max_kg * 2 : $this->dumbbell_max_kg,
            LoadType::Bodyweight => null,
        };
    }
}
