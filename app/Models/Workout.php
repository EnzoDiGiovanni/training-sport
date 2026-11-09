<?php

namespace App\Models;

use App\Enums\WorkoutType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => WorkoutType::class, 'archived_at' => 'datetime'];
    }

    public function scopeActive(Builder $q): void
    {
        $q->whereNull('archived_at');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('position');
    }

    /** Segments de premier niveau ; les blocs « repeat » portent leurs enfants via children. */
    public function segments(): HasMany
    {
        return $this->hasMany(RunSegment::class)->whereNull('parent_id')->orderBy('position');
    }

    public function allSegments(): HasMany
    {
        return $this->hasMany(RunSegment::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TrainingDay::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }
}
