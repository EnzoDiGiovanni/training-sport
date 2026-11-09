<?php

use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/** Utilisateur unique + bibliothèque + programme du prototype. */
function owner(): User
{
    $user = UserSeeder::createOwner('Enzo', 'enzo@test.local', 'password-de-test');
    (new ExerciseSeeder)->run();
    (new ProgramSeeder)->run();

    return $user->fresh();
}

function workout(string $name): Workout
{
    return Workout::where('name', $name)->firstOrFail();
}
