<?php

namespace App\Models;

use App\Enums\SegmentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RunSegment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => SegmentKind::class, 'duration_min' => 'float', 'speed_min_kmh' => 'float',
            'speed_max_kmh' => 'float', 'incline_pct' => 'float',
        ];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(RunSegment::class, 'parent_id')->orderBy('position');
    }
}
