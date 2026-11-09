<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Programme théorique (exercises, training_programs, workouts, training_days, workout_exercises, run_segments)
 * strictement séparé de l'historique (workout_sessions, session_exercises, exercise_sets, running_records).
 * Aucune cascade du programme vers l'historique : les clés sont nullOnDelete et l'historique garde des snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('height_cm')->default(167);
            $table->decimal('target_weight_kg', 5, 2)->nullable();
            $table->unsignedInteger('steps_min')->default(7000);
            $table->unsignedInteger('steps_max')->default(13000);
            $table->decimal('run_level_kmh', 4, 1)->default(9);
            $table->decimal('treadmill_max_kmh', 4, 1)->default(10);
            $table->decimal('treadmill_incline_max', 4, 1)->default(12);
            $table->decimal('dumbbell_max_kg', 5, 2)->default(10);
            $table->unsignedTinyInteger('dumbbell_count')->default(2);
            $table->boolean('dumbbells_combinable')->default(true);
            $table->decimal('weight_step_kg', 4, 2)->default(1);
            $table->boolean('has_bench')->default(true);
            $table->boolean('bench_incline')->default(true);
            $table->unsignedTinyInteger('weekly_sessions_goal')->default(5);
            $table->decimal('weekly_km_goal', 5, 1)->default(15);
            $table->unsignedSmallInteger('default_rest_seconds')->default(90);
            $table->json('goals')->nullable();
            $table->timestamps();
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('primary_muscle');
            $table->string('secondary_muscles')->nullable();
            $table->enum('muscle_group', ['chest', 'back', 'legs', 'shoulders', 'arms', 'core']);
            $table->string('equipment')->nullable();
            $table->text('description')->nullable();
            $table->json('instructions')->nullable();
            $table->json('common_mistakes')->nullable();
            $table->enum('measurement', ['reps', 'time'])->default('reps');
            $table->enum('load_type', ['two_dumbbells', 'one_dumbbell', 'bodyweight'])->default('two_dumbbells');
            $table->boolean('is_unilateral')->default(false);
            $table->string('image_path')->nullable();
            $table->string('illustration_key')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['run', 'strength', 'recovery', 'rest']);
            $table->text('objective')->nullable();
            $table->unsignedSmallInteger('estimated_duration_min')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('training_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = lundi … 7 = dimanche
            $table->foreignId('workout_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['training_program_id', 'day_of_week']);
            $table->timestamps();
        });

        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedTinyInteger('sets');
            $table->unsignedSmallInteger('reps_min');
            $table->unsignedSmallInteger('reps_max');
            $table->decimal('target_weight_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->default(90);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('run_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('run_segments')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->enum('kind', ['warmup', 'run', 'fast', 'recovery', 'cooldown', 'repeat']);
            $table->string('label')->nullable();
            $table->decimal('duration_min', 5, 2)->nullable();
            $table->decimal('speed_min_kmh', 4, 1)->nullable();
            $table->decimal('speed_max_kmh', 4, 1)->nullable();
            $table->decimal('incline_pct', 4, 1)->nullable();
            $table->unsignedSmallInteger('repeat_count')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('workout_name');
            $table->enum('type', ['run', 'strength', 'recovery', 'rest']);
            $table->date('performed_on');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->unsignedTinyInteger('perceived_effort')->nullable();
            $table->text('comment')->nullable();
            $table->string('activity')->nullable();
            $table->json('planned_snapshot')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'performed_on']);
        });

        Schema::create('session_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exercise_name');
            $table->enum('measurement', ['reps', 'time']);
            $table->enum('load_type', ['two_dumbbells', 'one_dumbbell', 'bodyweight']);
            $table->boolean('is_unilateral')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedTinyInteger('target_sets');
            $table->unsignedSmallInteger('target_reps_min');
            $table->unsignedSmallInteger('target_reps_max');
            $table->decimal('target_weight_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->default(90);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->decimal('weight_kg', 5, 2)->default(0); // par haltère pour two_dumbbells
            $table->unsignedSmallInteger('reps')->default(0); // ou secondes
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('running_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('distance_km', 5, 2);
            $table->decimal('max_speed_kmh', 4, 1)->nullable();
            $table->decimal('incline_pct', 4, 1)->nullable();
            $table->unsignedSmallInteger('cycles_completed')->nullable();
            $table->unsignedSmallInteger('cycles_planned')->nullable();
            $table->timestamps();
        });

        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 2);
            $table->unique(['user_id', 'measured_on']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['weight_records', 'running_records', 'exercise_sets', 'session_exercises', 'workout_sessions', 'run_segments',
            'workout_exercises', 'training_days', 'workouts', 'training_programs', 'exercises', 'user_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
