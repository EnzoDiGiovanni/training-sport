<?php

use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\TrainingDay;
use App\Models\WeightRecord;
use Illuminate\Database\QueryException;

it('seeds the prototype program with relations', function () {
    $user = owner();
    $program = $user->activeProgram;

    expect(Exercise::count())->toBe(13)
        ->and($program->workouts)->toHaveCount(6)
        ->and($program->days)->toHaveCount(7)
        ->and($program->days->firstWhere('day_of_week', 7)->workout_id)->toBeNull()
        ->and(workout('Musculation A')->exercises)->toHaveCount(5)
        ->and(workout('Musculation B')->exercises->first()->exercise->slug)->toBe('rdl');

    $frac = workout('Fractionné');
    $block = $frac->segments->firstWhere('kind.value', 'repeat');
    expect($frac->segments)->toHaveCount(3)
        ->and($block->repeat_count)->toBe(5)
        ->and($block->children->pluck('speed_max_kmh')->all())->toBe([10.0, 7.0]);
});

it('enforces one schedule entry per day', function () {
    $user = owner();
    TrainingDay::create(['training_program_id' => $user->activeProgram->id, 'day_of_week' => 1]);
})->throws(QueryException::class);

it('keeps one weight record per user and day', function () {
    $user = owner();
    WeightRecord::create(['user_id' => $user->id, 'measured_on' => today(), 'weight_kg' => 70]);
})->throws(QueryException::class);

it('never cascades program deletions to the history', function () {
    $user = owner();
    $w = workout('Musculation A');
    $session = $user->sessions()->create([
        'workout_id' => $w->id, 'workout_name' => $w->name, 'type' => 'strength', 'performed_on' => today(),
        'status' => SessionStatus::Completed,
    ]);
    $item = $session->exercises()->create([
        'exercise_id' => $w->exercises->first()->exercise_id, 'exercise_name' => 'Développé couché haltères',
        'measurement' => 'reps', 'load_type' => 'two_dumbbells', 'target_sets' => 3, 'target_reps_min' => 8, 'target_reps_max' => 12,
    ]);
    $item->sets()->create(['set_number' => 1, 'weight_kg' => 10, 'reps' => 10, 'is_completed' => true]);

    $w->delete();

    expect($session->fresh()->workout_id)->toBeNull()
        ->and($session->fresh()->workout_name)->toBe('Musculation A')
        ->and($item->fresh()->sets)->toHaveCount(1);
});

it('refuses to delete an exercise still used by the program', function () {
    owner();
    Exercise::where('slug', 'row')->first()->delete();
})->throws(QueryException::class);
