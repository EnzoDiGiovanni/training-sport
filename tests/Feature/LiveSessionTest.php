<?php

use App\Enums\SessionStatus;
use App\Models\WorkoutSession;
use App\Services\SessionInProgressException;
use App\Services\SessionStarter;

/** Termine une séance de musculation en validant toutes les séries avec la charge donnée. */
function finishStrength($test, $user, string $name, ?float $kg = null): WorkoutSession
{
    $test->actingAs($user)->post('/seances/'.workout($name)->id.'/demarrer')->assertRedirect('/seance-en-cours');
    $session = SessionStarter::current($user)->load('exercises.sets');
    $test->putJson('/api/live', ['exercises' => $session->exercises->map(fn ($e) => ['id' => $e->id, 'note' => null, 'sets' => $e->sets->map(fn ($s) => [
        'weight_kg' => $kg ?? $s->weight_kg, 'reps' => $s->reps, 'is_completed' => true,
    ])->all()])->all()])->assertOk();
    $test->post('/seance-en-cours/terminer', ['rpe' => 3])->assertRedirect('/');

    return $session->fresh();
}

it('snapshots the program: editing the workout afterwards never changes a performed session', function () {
    $user = owner();
    $session = finishStrength($this, $user, 'Musculation A');
    $w = workout('Musculation A');

    $this->put("/programme/seances/{$w->id}", ['name' => 'Muscu A renommée', 'type' => 'strength', 'days' => [2], 'segments' => [],
        'exercises' => [['exercise_id' => $w->exercises[1]->exercise_id, 'sets' => 5, 'reps_min' => 6, 'reps_max' => 8, 'target_weight_kg' => 20, 'rest_seconds' => 120]],
    ])->assertSessionHasNoErrors();

    $session->refresh()->load('exercises.sets');
    expect($session->workout_name)->toBe('Musculation A')
        ->and($session->exercises)->toHaveCount(5)
        ->and($session->exercises[0]->exercise_name)->toBe('Développé couché haltères')
        ->and($session->exercises[0]->target_sets)->toBe(3)
        ->and($session->exercises[0]->sets)->toHaveCount(3)
        ->and($session->planned_snapshot['exercises'][0]['reps_max'])->toBe(12);
});

it('prefills sets from the last performance, set by set, otherwise from program targets', function () {
    $user = owner();
    $first = app(SessionStarter::class)->start($user, workout('Musculation A'));
    $bench = $first->exercises()->with('sets')->first();
    expect($bench->sets->pluck('weight_kg')->all())->toBe([10.0, 10.0, 10.0])
        ->and($bench->sets->pluck('reps')->all())->toBe([12, 12, 12]);

    // Dernière performance : 9 × 12, 9 × 10, 8 × 9
    $bench->sets->each(fn ($s, $i) => $s->update(['weight_kg' => [9, 9, 8][$i], 'reps' => [12, 10, 9][$i], 'is_completed' => true]));
    $first->update(['status' => SessionStatus::Completed]);

    $next = app(SessionStarter::class)->start($user, workout('Musculation A'));
    $sets = $next->exercises()->with('sets')->first()->sets;
    expect($sets->pluck('weight_kg')->all())->toBe([9.0, 9.0, 8.0])->and($sets->pluck('reps')->all())->toBe([12, 10, 9]);
});

it('allows only one in-progress session at a time', function () {
    $user = owner();
    $starter = app(SessionStarter::class);
    $a = $starter->start($user, workout('Musculation A'));

    expect(fn () => $starter->start($user, workout('Fractionné')))->toThrow(SessionInProgressException::class);

    $this->actingAs($user)->post('/seances/'.workout('Fractionné')->id.'/demarrer')->assertRedirect('/seance-en-cours');
    expect(WorkoutSession::where('status', 'in_progress')->count())->toBe(1);

    $this->post('/seances/'.workout('Fractionné')->id.'/demarrer', ['abandon_current' => true]);
    expect($a->fresh()->status)->toBe(SessionStatus::Abandoned)
        ->and(SessionStarter::current($user)->workout_name)->toBe('Fractionné')
        ->and(WorkoutSession::where('status', 'in_progress')->count())->toBe(1);
});

it('autosaves sets and survives a reload; completing keeps only validated sets', function () {
    $user = owner();
    $this->actingAs($user)->post('/seances/'.workout('Musculation B')->id.'/demarrer');
    $s = SessionStarter::current($user)->load('exercises.sets');
    $first = $s->exercises[0];
    $payload = $s->exercises->map(fn ($e) => ['id' => $e->id, 'note' => null, 'sets' => $e->sets->map(fn ($x) => ['weight_kg' => $x->weight_kg, 'reps' => $x->reps, 'is_completed' => false])->all()])->all();
    $payload[0]['note'] = 'Banc cran 2';
    $payload[0]['sets'][0] = ['weight_kg' => 10, 'reps' => 11, 'is_completed' => true];
    $payload[0]['sets'][] = ['weight_kg' => 10, 'reps' => 8, 'is_completed' => false];
    $this->putJson('/api/live', ['exercises' => $payload])->assertOk()->assertJson(['volume' => 220]);

    $this->get('/seance-en-cours')->assertInertia(fn ($p) => $p->component('Live/Strength')
        ->where('session.exercises.0.note', 'Banc cran 2')
        ->has('session.exercises.0.sets', 4)
        ->where('session.exercises.0.sets.0.is_completed', true));

    $this->post('/seance-en-cours/terminer')->assertRedirect('/');
    expect($first->fresh()->sets)->toHaveCount(1)->and($s->fresh()->exercises)->toHaveCount(1)
        ->and($s->fresh()->status)->toBe(SessionStatus::Completed);
});

it('refuses to complete a strength session without any validated set', function () {
    $user = owner();
    $this->actingAs($user)->post('/seances/'.workout('Musculation A')->id.'/demarrer');
    $this->post('/seance-en-cours/terminer')->assertSessionHasErrors('session');
    expect(SessionStarter::current($user))->not->toBeNull();
});

it('records a run, computes the average speed and rejects speeds above the treadmill', function () {
    $user = owner();
    $this->actingAs($user)->post('/seances/'.workout('Fractionné')->id.'/demarrer');

    // 6 km en 30 min = 12 km/h > tapis 10 km/h
    $this->post('/seance-en-cours/course', ['duration' => '30:00', 'distance_km' => 6])->assertSessionHasErrors('distance_km');
    $this->post('/seance-en-cours/course', ['duration' => '30:00', 'distance_km' => 4.5, 'max_speed_kmh' => 11])->assertSessionHasErrors('max_speed_kmh');
    $this->post('/seance-en-cours/course', ['duration' => '30:00', 'distance_km' => 4.5, 'max_speed_kmh' => 8])->assertSessionHasErrors('max_speed_kmh');
    $this->post('/seance-en-cours/course', ['duration' => 'abc', 'distance_km' => 4.5])->assertSessionHasErrors('duration');
    $this->post('/seance-en-cours/course', ['duration' => '30:00', 'distance_km' => 4.5, 'incline_pct' => 20])->assertSessionHasErrors('incline_pct');

    $this->post('/seance-en-cours/course', ['duration' => '30:00', 'distance_km' => 4.5, 'max_speed_kmh' => 10, 'incline_pct' => 0, 'cycles_completed' => 4, 'rpe' => 4])
        ->assertRedirect('/seance-en-cours/recap');
    $session = SessionStarter::current($user)->load('run');
    expect($session->run->average_speed_kmh)->toBe(9.0)
        ->and($session->run->cycles_planned)->toBe(5)
        ->and($session->duration_seconds)->toBe(1800);

    $this->get('/seance-en-cours/recap')->assertInertia(fn ($p) => $p->component('Live/Summary')->where('kcal', 299));
    $this->post('/seance-en-cours/terminer')->assertRedirect('/');
    expect($session->fresh()->status)->toBe(SessionStatus::Completed);
});

it('detects personal records against the same workout only', function () {
    $user = owner();
    finishStrength($this, $user, 'Musculation A', 8);
    $this->post('/seances/'.workout('Musculation A')->id.'/demarrer');
    $s = SessionStarter::current($user)->load('exercises.sets');
    $this->putJson('/api/live', ['exercises' => $s->exercises->map(fn ($e) => ['id' => $e->id, 'sets' => $e->sets->map(fn ($x) => ['weight_kg' => 9, 'reps' => $x->reps, 'is_completed' => true])->all()])->all()]);
    $this->get('/seance-en-cours/recap')->assertInertia(fn ($p) => $p->where('records.0', 'Développé couché haltères : nouvelle charge max, 9 kg (avant 8 kg)'));
});

it('logs a recovery activity', function () {
    $user = owner();
    $this->actingAs($user)->post('/recuperation', ['activity' => 'Marche', 'duration_min' => 30, 'performed_on' => today()->toDateString(), 'workout_id' => workout('Récupération active')->id])
        ->assertSessionHasNoErrors();
    expect($user->sessions()->first())->type->value->toBe('recovery')->duration_seconds->toBe(1800);
});

it('starts a late session on the planned past day, never in the future', function () {
    $user = owner();
    $past = today()->subDays(2)->toDateString();
    $this->actingAs($user)->post('/seances/'.workout('Musculation A')->id.'/demarrer', ['date' => $past])
        ->assertRedirect('/seance-en-cours');
    expect(SessionStarter::current($user)->performed_on->toDateString())->toBe($past);

    $this->post('/seances/'.workout('Fractionné')->id.'/demarrer', ['date' => today()->addDay()->toDateString(), 'abandon_current' => true])
        ->assertSessionHasErrors('date');
    expect(SessionStarter::current($user)->workout_name)->toBe('Musculation A');
});
