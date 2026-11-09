<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\WeightController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Séances type (programme)
    Route::get('seances/{workout}', [WorkoutController::class, 'show'])->name('workouts.show');

    // Séance en cours
    Route::post('seances/{workout}/demarrer', [LiveSessionController::class, 'start'])->name('live.start');
    Route::get('seance-en-cours', [LiveSessionController::class, 'show'])->name('live.show');
    Route::post('seance-en-cours/course', [LiveSessionController::class, 'saveRun'])->name('live.run');
    Route::get('seance-en-cours/recap', [LiveSessionController::class, 'summary'])->name('live.summary');
    Route::post('seance-en-cours/terminer', [LiveSessionController::class, 'complete'])->name('live.complete');
    Route::delete('seance-en-cours', [LiveSessionController::class, 'abandon'])->name('live.abandon');
    Route::post('recuperation', [LiveSessionController::class, 'recovery'])->name('live.recovery');

    // Historique
    Route::get('entrainements', [SessionController::class, 'index'])->name('sessions.index');
    Route::put('entrainements/{session}', [SessionController::class, 'update'])->name('sessions.update');
    Route::delete('entrainements/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    // Progression et pesées
    Route::get('progression', StatsController::class)->name('stats');
    Route::post('pesees', [WeightController::class, 'store'])->name('weights.store');
    Route::delete('pesees/{weight}', [WeightController::class, 'destroy'])->name('weights.destroy');

    // Programme
    Route::get('programme', [ProgramController::class, 'index'])->name('program.index');
    Route::put('programme/jours/{day}', [ProgramController::class, 'schedule'])->whereNumber('day')->name('program.schedule');
    Route::get('programme/seances/nouvelle', [ProgramController::class, 'create'])->name('program.create');
    Route::post('programme/seances', [ProgramController::class, 'store'])->name('program.store');
    Route::get('programme/seances/{workout}', [ProgramController::class, 'edit'])->name('program.edit');
    Route::put('programme/seances/{workout}', [ProgramController::class, 'update'])->name('program.update');
    Route::post('programme/seances/{workout}/dupliquer', [ProgramController::class, 'duplicate'])->name('program.duplicate');
    Route::delete('programme/seances/{workout}', [ProgramController::class, 'destroy'])->name('program.destroy');

    // Bibliothèque d'exercices
    Route::get('exercices', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('exercices', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::post('exercices/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update'); // POST : multipart avec image
    Route::delete('exercices/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');

    // Paramètres
    Route::get('parametres', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('parametres', [SettingsController::class, 'update'])->name('settings.update');

    // JSON (session web, pas de token) : sauvegarde automatique de la séance en cours et historique d'un exercice
    Route::prefix('api')->group(function () {
        Route::put('live', [LiveSessionController::class, 'autosave'])->name('api.live.autosave');
        Route::get('exercises/{exercise}/history', [ExerciseController::class, 'history'])->name('api.exercises.history');
    });
});
