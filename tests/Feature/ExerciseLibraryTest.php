<?php

use App\Models\Exercise;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

$payload = fn (array $o = []) => $o + [
    'name' => 'Élévations latérales', 'primary_muscle' => 'Épaules', 'muscle_group' => 'shoulders',
    'measurement' => 'reps', 'load_type' => 'two_dumbbells', 'is_unilateral' => 0,
    'instructions' => ['Coudes légèrement fléchis', ''], 'common_mistakes' => [],
];

it('creates a custom exercise with an imported image', function () use ($payload) {
    Storage::fake('public');
    $user = owner();
    $this->actingAs($user)->post('/exercices', $payload(['image' => UploadedFile::fake()->image('lat.jpg', 320, 200)]))
        ->assertRedirect()->assertSessionHasNoErrors();

    $ex = Exercise::where('name', 'Élévations latérales')->first();
    expect($ex->is_custom)->toBeTrue()->and($ex->user_id)->toBe($user->id)
        ->and($ex->instructions)->toBe(['Coudes légèrement fléchis'])
        ->and($ex->image_url)->toContain('/storage/exercises/');
    Storage::disk('public')->assertExists($ex->image_path);
});

it('rejects non-image uploads and oversize files', function () use ($payload) {
    Storage::fake('public');
    $this->actingAs(owner())->post('/exercices', $payload(['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors('image');
    $this->post('/exercices', $payload(['image' => UploadedFile::fake()->image('big.png')->size(3000)]))
        ->assertSessionHasErrors('image');
});

it('refuses deleting a custom exercise used in the program, archives it when it has history', function () use ($payload) {
    $user = owner();
    $this->actingAs($user)->post('/exercices', $payload());
    $ex = Exercise::where('is_custom', true)->first();
    $we = workout('Musculation A')->exercises()->create(['exercise_id' => $ex->id, 'sets' => 3, 'reps_min' => 10, 'reps_max' => 12, 'rest_seconds' => 60]);

    $this->delete("/exercices/{$ex->id}")->assertSessionHasErrors('exercise');
    expect($ex->fresh())->not->toBeNull();

    $we->delete();
    $user->sessions()->create(['workout_name' => 'X', 'type' => 'strength', 'performed_on' => today(), 'status' => 'completed'])
        ->exercises()->create(['exercise_id' => $ex->id, 'exercise_name' => $ex->name, 'measurement' => 'reps', 'load_type' => 'two_dumbbells', 'target_sets' => 1, 'target_reps_min' => 1, 'target_reps_max' => 1]);
    $this->delete("/exercices/{$ex->id}")->assertSessionHasNoErrors();
    expect($ex->fresh()->archived_at)->not->toBeNull();
});

it('does not let the user edit built-in exercises', function () use ($payload) {
    $this->actingAs(owner())->post('/exercices/'.Exercise::where('slug', 'row')->value('id'), $payload())->assertNotFound();
});

it('renders the library with all base exercises', function () {
    $this->actingAs(owner())->get('/exercices')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Exercises/Index')->has('exercises', 13));
});
