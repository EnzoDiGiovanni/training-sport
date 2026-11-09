<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingDay extends Model
{
    protected $guarded = ['id'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
