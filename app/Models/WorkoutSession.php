<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\WorkoutType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkoutSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => WorkoutType::class, 'status' => SessionStatus::class, 'performed_on' => 'date:Y-m-d',
            'started_at' => 'datetime', 'ended_at' => 'datetime', 'planned_snapshot' => 'array',
        ];
    }

    public function scopeCompleted(Builder $q): void
    {
        $q->where('status', SessionStatus::Completed);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(SessionExercise::class)->orderBy('position');
    }

    public function run(): HasOne
    {
        return $this->hasOne(RunningRecord::class);
    }
}
