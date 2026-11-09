<?php

use App\Enums\LoadType;
use App\Models\ExerciseSet;
use App\Models\SessionExercise;
use App\Models\User;
use App\Services\RunMetrics;
use App\Services\StatsService;
use App\Services\VolumeCalculator;
use App\Services\WeekStatus;
use Illuminate\Support\Carbon;

it('computes volume by load type and unilateral factor', function () {
    expect(VolumeCalculator::set(10, 10, LoadType::TwoDumbbells, false))->toEqual(200)
        ->and(VolumeCalculator::set(20, 10, LoadType::OneDumbbell, false))->toEqual(200)
        ->and(VolumeCalculator::set(20, 10, LoadType::OneDumbbell, true))->toEqual(400)
        ->and(VolumeCalculator::set(8, 10, LoadType::TwoDumbbells, true))->toEqual(320)
        ->and(VolumeCalculator::set(0, 20, LoadType::Bodyweight, false))->toEqual(0);

    $item = new SessionExercise(['load_type' => LoadType::TwoDumbbells, 'is_unilateral' => false]);
    $item->setRelation('sets', collect([
        new ExerciseSet(['weight_kg' => 10, 'reps' => 10, 'is_completed' => true]),
        new ExerciseSet(['weight_kg' => 10, 'reps' => 10, 'is_completed' => false]),
    ]));
    expect(VolumeCalculator::exercise($item))->toEqual(200);
});

it('computes run metrics', function () {
    expect(RunMetrics::averageSpeed(4.5, 1800))->toBe(9.0)
        ->and(RunMetrics::averageSpeed(4.5, 0))->toBeNull()
        ->and(RunMetrics::parseClock('30:00'))->toBe(1800)
        ->and(RunMetrics::parseClock('1:05:30'))->toBe(3930)
        ->and(RunMetrics::parseClock('30'))->toBeNull()
        ->and(RunMetrics::parseClock('12:75'))->toBeNull();

    $tree = [
        ['kind' => 'warmup', 'duration_min' => 5, 'speed_min_kmh' => 6, 'speed_max_kmh' => 7],
        ['kind' => 'repeat', 'repeat_count' => 5, 'children' => [
            ['kind' => 'fast', 'duration_min' => 1, 'speed_min_kmh' => 9.5, 'speed_max_kmh' => 10],
            ['kind' => 'recovery', 'duration_min' => 2, 'speed_min_kmh' => 7, 'speed_max_kmh' => 7],
        ]],
    ];
    expect(RunMetrics::plannedMinutes($tree))->toEqual(20)
        ->and(round(RunMetrics::estimatedKm($tree), 3))->toEqual(round(5 / 60 * 6.5 + 5 * (9.75 / 60 + 2 / 60 * 7), 3))
        ->and(RunMetrics::plannedCycles($tree))->toBe(5);
});

it('computes the week status: done, missed, today, upcoming, rest, recovery', function () {
    Carbon::setTestNow('2026-11-12 10:00'); // jeudi
    $user = owner();
    $monday = today()->startOfWeek();
    // Lundi (course) réalisé, mardi (muscu A) manqué, mercredi (fractionné) manqué
    $user->sessions()->create(['workout_id' => workout('Endurance fondamentale')->id, 'workout_name' => 'Endurance fondamentale', 'type' => 'run', 'performed_on' => '2026-11-09', 'status' => 'completed']);

    expect(array_column(WeekStatus::for($user, $monday), 'status'))
        ->toBe(['done', 'missed', 'missed', 'today', 'upcoming', 'upcoming', 'rest']);

    Carbon::setTestNow('2026-11-14 10:00'); // samedi
    expect(WeekStatus::for($user, $monday)[3]['status'])->toBe('recovery')
        ->and(WeekStatus::for($user, $monday)[4]['status'])->toBe('missed')
        ->and(WeekStatus::for($user, $monday)[5]['status'])->toBe('today');
    Carbon::setTestNow();
});

it('reflects program changes immediately on the dashboard', function () {
    $user = owner();
    $day = today()->dayOfWeekIso;
    $this->actingAs($user)->put("/programme/jours/{$day}", ['workout_id' => workout('Musculation B')->id]);
    $this->get('/')->assertInertia(fn ($p) => $p->component('Dashboard')->where('week.'.($day - 1).'.workout_id', workout('Musculation B')->id));
});

it('replaces a same-day weight record and computes weight stats', function () {
    Carbon::setTestNow('2026-11-09 08:00');
    $user = owner(); // pesée 66,5 kg aujourd'hui
    $this->actingAs($user)->post('/pesees', ['measured_on' => '2026-11-09', 'weight_kg' => 66.2])->assertSessionHasNoErrors();
    expect($user->weights()->count())->toBe(1)->and($user->currentWeight())->toBe(66.2);

    $this->post('/pesees', ['measured_on' => '2026-11-20', 'weight_kg' => 66])->assertSessionHasErrors('measured_on');
    $this->post('/pesees', ['measured_on' => '2026-11-08', 'weight_kg' => 20])->assertSessionHasErrors('weight_kg');

    // Perte rapide : 70 → 66,2 kg en 3 semaines
    foreach (['2026-10-19' => 70, '2026-10-26' => 69, '2026-11-02' => 67.8] as $d => $kg) {
        $user->weights()->create(['measured_on' => $d, 'weight_kg' => $kg]);
    }
    $w = StatsService::weight($user);
    expect($w['last']['kg'])->toBe(66.2)
        ->and($w['delta30'])->toBe(-3.8)
        ->and($w['too_fast'])->toBeTrue()
        ->and(StatsService::movingAverage([['date' => '2026-01-01', 'kg' => 70], ['date' => '2026-01-03', 'kg' => 68], ['date' => '2026-01-10', 'kg' => 66]]))
        ->toBe([70, 69, 66]);
    Carbon::setTestNow();
});

it('saves settings, validates per field and logs a weigh-in when the weight changes', function () {
    $user = owner();
    $base = ['name' => 'Enzo', 'height_cm' => 167, 'weight' => 66.5, 'target_weight_kg' => 64, 'weekly_sessions_goal' => 5, 'weekly_km_goal' => 15,
        'goals' => ['Courir 10 km'], 'steps_min' => 7000, 'steps_max' => 13000, 'run_level_kmh' => 9, 'treadmill_max_kmh' => 12,
        'treadmill_incline_max' => 12, 'dumbbell_max_kg' => 12.5, 'dumbbell_count' => 2, 'weight_step_kg' => 0.5,
        'dumbbells_combinable' => true, 'has_bench' => true, 'bench_incline' => false, 'default_rest_seconds' => 75];
    $this->actingAs($user)->put('/parametres', ['steps_max' => 5000, 'treadmill_max_kmh' => 50] + $base)->assertSessionHasErrors(['steps_max', 'treadmill_max_kmh']);
    $this->put('/parametres', ['weight' => 65.9] + $base)->assertSessionHasNoErrors();

    $p = $user->profile->fresh();
    expect($p->treadmill_max_kmh)->toBe(12.0)->and($p->dumbbell_max_kg)->toBe(12.5)->and($p->bench_incline)->toBeFalse()
        ->and($p->goals)->toBe(['Courir 10 km'])->and($user->currentWeight())->toBe(65.9)->and($user->weights()->count())->toBe(1);
});

it('only exposes the owner data', function () {
    $user = owner();
    $other = User::factory()->create();
    $session = $user->sessions()->create(['workout_name' => 'X', 'type' => 'recovery', 'performed_on' => today(), 'status' => 'completed']);
    $this->actingAs($other)->delete("/entrainements/{$session->id}")->assertNotFound();
    $this->actingAs($other)->get('/seances/'.workout('Fractionné')->id)->assertNotFound();
});
