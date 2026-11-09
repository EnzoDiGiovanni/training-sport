<?php

namespace App\Models;

use App\Enums\ExerciseMeasurement;
use App\Enums\LoadType;
use App\Enums\MuscleGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Exercise extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'muscle_group' => MuscleGroup::class, 'measurement' => ExerciseMeasurement::class, 'load_type' => LoadType::class,
            'instructions' => 'array', 'common_mistakes' => 'array', 'is_unilateral' => 'boolean', 'is_custom' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** Exercices de base (user_id null) + exercices personnels de l'utilisateur. */
    public function scopeVisibleTo(Builder $q, User $user): void
    {
        $q->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function sessionExercises(): HasMany
    {
        return $this->hasMany(SessionExercise::class);
    }
}
