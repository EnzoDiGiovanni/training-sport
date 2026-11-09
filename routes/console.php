<?php

use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('app:create-user {--name=} {--email=} {--password=}', function () {
    $name = $this->option('name') ?: text('Prénom', default: 'Enzo', required: true);
    $email = $this->option('email') ?: text('Email', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email invalide.');
    $password = $this->option('password') ?: password('Mot de passe (12 caractères min.)', required: true, validate: fn ($v) => strlen($v) >= 12 ? null : '12 caractères minimum.');

    $user = UserSeeder::createOwner($name, $email, $password);
    $this->info("Utilisateur {$user->email} prêt.");
})->purpose("Crée ou met à jour l'utilisateur unique de l'application");

// Sauvegarde nocturne hors du VPS (base + images), rétention 7 quotidiennes / 4 hebdomadaires.
Schedule::command('backup:clean')->dailyAt('02:30');
Schedule::command('backup:run')->dailyAt('03:00');
Schedule::command('backup:monitor')->dailyAt('09:00');
