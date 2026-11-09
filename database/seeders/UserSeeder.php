<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('APP_OWNER_EMAIL');
        $password = env('APP_OWNER_PASSWORD');
        if (! $email || ! $password) {
            throw new RuntimeException('Renseigne APP_OWNER_EMAIL et APP_OWNER_PASSWORD dans .env.');
        }

        self::createOwner(env('APP_OWNER_NAME', 'Enzo'), $email, $password);
    }

    /** Crée (ou met à jour) l'utilisateur unique avec le profil de la section 1 et une première pesée. */
    public static function createOwner(string $name, string $email, string $password): User
    {
        $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
        $user->profile()->firstOrCreate([], [
            'height_cm' => 167, 'target_weight_kg' => 64, 'steps_min' => 7000, 'steps_max' => 13000,
            'run_level_kmh' => 9, 'treadmill_max_kmh' => 10, 'treadmill_incline_max' => 12,
            'dumbbell_max_kg' => 10, 'dumbbell_count' => 2, 'dumbbells_combinable' => true, 'weight_step_kg' => 1,
            'has_bench' => true, 'bench_incline' => true, 'weekly_sessions_goal' => 5, 'weekly_km_goal' => 15,
            'default_rest_seconds' => 90,
            'goals' => ['Perdre progressivement de la masse grasse', 'Développer la masse musculaire', 'Améliorer l’endurance cardiovasculaire'],
        ]);
        if (! $user->weights()->exists()) {
            $user->weights()->create(['measured_on' => today(), 'weight_kg' => 66.5]);
        }

        return $user;
    }
}
