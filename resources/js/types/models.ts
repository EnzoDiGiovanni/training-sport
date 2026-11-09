export type WorkoutType = 'run' | 'strength' | 'recovery' | 'rest';
export type LoadType = 'two_dumbbells' | 'one_dumbbell' | 'bodyweight';
export type Measurement = 'reps' | 'time';
export type SegmentKind = 'warmup' | 'run' | 'fast' | 'recovery' | 'cooldown';

export type LiveRef = { id: number; name: string; type: WorkoutType; started_at: string | null };

export type Exercise = {
    id: number;
    name: string;
    slug: string;
    primary_muscle: string;
    secondary_muscles: string | null;
    muscle_group: 'chest' | 'back' | 'legs' | 'shoulders' | 'arms' | 'core';
    equipment: string | null;
    description: string | null;
    instructions: string[] | null;
    common_mistakes: string[] | null;
    measurement: Measurement;
    load_type: LoadType;
    is_unilateral: boolean;
    image_url: string | null;
    illustration_key: string | null;
    is_custom: boolean;
    archived_at: string | null;
};

export type Step = {
    kind: SegmentKind;
    label: string;
    duration_min: number;
    speed_min_kmh: number;
    speed_max_kmh: number;
    incline_pct: number;
    cycle?: number;
    of?: number;
};
export type RepeatBlock = { kind: 'repeat'; repeat_count: number; children: Step[] };
export type Segment = Step | RepeatBlock;

export type WorkoutExercise = {
    id?: number;
    exercise_id: number;
    sets: number;
    reps_min: number;
    reps_max: number;
    target_weight_kg: number | null;
    rest_seconds: number;
    notes?: string | null;
};

export type Workout = {
    id: number;
    name: string;
    type: WorkoutType;
    objective: string | null;
    estimated_duration_min: number | null;
    exercises: WorkoutExercise[];
    segments: Segment[];
    days: number[];
};

export type Profile = {
    height_cm: number;
    target_weight_kg: number | null;
    steps_min: number;
    steps_max: number;
    run_level_kmh: number;
    treadmill_max_kmh: number;
    treadmill_incline_max: number;
    dumbbell_max_kg: number;
    dumbbell_count: number;
    dumbbells_combinable: boolean;
    weight_step_kg: number;
    has_bench: boolean;
    bench_incline: boolean;
    weekly_sessions_goal: number;
    weekly_km_goal: number;
    default_rest_seconds: number;
    goals: string[] | null;
    name: string;
    weight: number | null;
};

export type SetRow = { id?: number; weight_kg: number; reps: number; is_completed: boolean };

export type SessionItem = {
    id: number;
    exercise_id: number | null;
    name: string;
    measurement: Measurement;
    load_type: LoadType;
    is_unilateral: boolean;
    note: string | null;
    target_sets: number;
    target_reps_min: number;
    target_reps_max: number;
    target_weight_kg: number | null;
    rest_seconds: number;
    illustration_key: string | null;
    image_url: string | null;
    sets: SetRow[];
};

export type RunRecord = {
    distance_km: number;
    max_speed_kmh: number | null;
    incline_pct: number | null;
    cycles_completed: number | null;
    cycles_planned: number | null;
    average_speed_kmh: number | null;
};

export type Session = {
    id: number;
    workout_id: number | null;
    name: string;
    type: WorkoutType;
    date: string;
    duration_seconds: number;
    rpe: number | null;
    comment: string | null;
    activity: string | null;
    run: RunRecord | null;
    exercises: SessionItem[];
    volume: number;
};

export type ExerciseHistoryPoint = {
    session_id: number;
    date: string;
    max_kg: number;
    reps: number;
    best_reps: number;
    volume: number;
    note: string | null;
    sets: { weight_kg: number; reps: number }[];
};
