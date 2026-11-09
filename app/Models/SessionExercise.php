<?php

namespace App\Models;

use App\Enums\ExerciseMeasurement;
use App\Enums\LoadType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionExercise extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'measurement' => ExerciseMeasurement::class, 'load_type' => LoadType::class,
            'is_unilateral' => 'boolean', 'target_weight_kg' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(ExerciseSet::class)->orderBy('set_number');
    }
}
