<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weight_kg' => 'float', 'is_completed' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(SessionExercise::class);
    }
}
