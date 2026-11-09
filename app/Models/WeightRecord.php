<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['measured_on' => DateOnly::class, 'weight_kg' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
