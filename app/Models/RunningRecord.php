<?php

namespace App\Models;

use App\Services\RunMetrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunningRecord extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['average_speed_kmh'];

    protected function casts(): array
    {
        return ['distance_km' => 'float', 'max_speed_kmh' => 'float', 'incline_pct' => 'float'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    /** Non stockée : distance / durée de la séance (règle 5). */
    public function getAverageSpeedKmhAttribute(): ?float
    {
        return RunMetrics::averageSpeed($this->distance_km, $this->session?->duration_seconds ?? 0);
    }
}
