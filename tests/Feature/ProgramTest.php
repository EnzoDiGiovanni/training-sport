<?php

use App\Models\Workout;

$strength = fn (array $o = []) => $o + [
    'name' => 'Musculation C', 'type' => 'strength', 'objective' => '', 'estimated_duration_min' => 40, 'days' => [7],
    'exercises' => [['exercise_id' => 1, 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'target_weight_kg' => 8, 'rest_seconds' => 90]],
    'segments' => [],
];

it('creates a strength workout and schedules it', function () use ($strength) {
    $user = owner();
    $this->actingAs($user)->post('/programme/seances', $strength())->assertRedirect('/programme');
    $w = Workout::where('name', 'Musculation C')->first();
    expect($w->exercises)->toHaveCount(1)
        ->and($user->activeProgram->days()->where('day_of_week', 7)->value('workout_id'))->toBe($w->id);
});

it('validates program rules: reps min ≤ max, at least one exercise, treadmill speed', function () use ($strength) {
    $this->actingAs(owner());
    $this->post('/programme/seances', $strength(['exercises' => []]))->assertSessionHasErrors('exercises');
    $bad = $strength();
    $bad['exercises'][0]['reps_min'] = 15;
    $this->post('/programme/seances', $bad)->assertSessionHasErrors('exercises');
    $bad['exercises'][0]['sets'] = 0;
    $this->post('/programme/seances', $bad)->assertSessionHasErrors('exercises.0.sets');

    $run = ['name' => 'Trop vite', 'type' => 'run', 'days' => [], 'exercises' => [], 'segments' => [
        ['kind' => 'run', 'label' => 'Sprint', 'duration_min' => 5, 'speed_min_kmh' => 9, 'speed_max_kmh' => 12, 'incline_pct' => 0],
    ]];
    $this->post('/programme/seances', $run)->assertSessionHasErrors('segments');
    $this->post('/programme/seances', ['segments' => []] + $run)->assertSessionHasErrors('segments');
});

it('saves a run workout with a repeat block and computes its duration', function () {
    $this->actingAs(owner())->post('/programme/seances', ['name' => 'Fractionné court', 'type' => 'run', 'days' => [], 'exercises' => [], 'segments' => [
        ['kind' => 'warmup', 'label' => 'Échauffement', 'duration_min' => 5, 'speed_min_kmh' => 6, 'speed_max_kmh' => 6, 'incline_pct' => 0],
        ['kind' => 'repeat', 'repeat_count' => 4, 'children' => [
            ['kind' => 'fast', 'label' => 'Vite', 'duration_min' => 1, 'speed_min_kmh' => 9.5, 'speed_max_kmh' => 10, 'incline_pct' => 0],
            ['kind' => 'recovery', 'label' => 'Récup', 'duration_min' => 2, 'speed_min_kmh' => 7, 'speed_max_kmh' => 7, 'incline_pct' => 0],
        ]],
    ]])->assertSessionHasNoErrors();
    $w = Workout::where('name', 'Fractionné court')->first();
    expect($w->estimated_duration_min)->toBe(17)->and($w->allSegments)->toHaveCount(4);
});

it('updates the week template', function () {
    $user = owner();
    $b = workout('Musculation B');
    $this->actingAs($user)->put('/programme/jours/7', ['workout_id' => $b->id])->assertRedirect();
    $this->put('/programme/jours/1', ['workout_id' => null]);
    expect($user->activeProgram->days()->where('day_of_week', 7)->value('workout_id'))->toBe($b->id)
        ->and($user->activeProgram->days()->where('day_of_week', 1)->value('workout_id'))->toBeNull();
});

it('archives a deleted workout with history and frees its days', function () {
    $user = owner();
    $w = workout('Musculation A');
    $user->sessions()->create(['workout_id' => $w->id, 'workout_name' => $w->name, 'type' => 'strength', 'performed_on' => today(), 'status' => 'completed']);
    $this->actingAs($user)->delete("/programme/seances/{$w->id}")->assertRedirect();

    expect($w->fresh()->archived_at)->not->toBeNull()
        ->and($user->sessions()->first()->workout_id)->toBe($w->id)
        ->and($user->activeProgram->days()->where('day_of_week', 2)->value('workout_id'))->toBeNull();
    $this->get('/programme')->assertInertia(fn ($p) => $p->has('workouts', 5));
});

it('really deletes a workout without history', function () {
    $user = owner();
    $w = workout('Fractionné');
    $this->actingAs($user)->delete("/programme/seances/{$w->id}");
    expect(Workout::find($w->id))->toBeNull();
});

it('duplicates a workout without scheduling it', function () {
    $this->actingAs(owner())->post('/programme/seances/'.workout('Fractionné')->id.'/dupliquer');
    $copy = workout('Fractionné (copie)');
    expect($copy->allSegments)->toHaveCount(5)->and($copy->days)->toHaveCount(0);
});
