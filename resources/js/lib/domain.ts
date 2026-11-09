// Règles d'affichage partagées, mêmes formules que les services PHP (le serveur reste la source de vérité).
import { fmt, fmtKg } from '@/composables/useFormat';
import type { Exercise, LoadType, Profile, Segment, SessionItem, SetRow, Step, Workout, WorkoutExercise, WorkoutType } from '@/types';

export const TYPE_LABEL: Record<WorkoutType, string> = { run: 'Course', strength: 'Musculation', recovery: 'Récupération', rest: 'Repos' };
export const TYPE_ICON: Record<WorkoutType, string> = { run: 'run', strength: 'dumbbell', recovery: 'leaf', rest: 'moon' };
export const KIND_COLOR: Record<string, string> = { warmup: '#5B6B8C', run: 'var(--run)', fast: 'var(--accent)', recovery: '#6E86B5', cooldown: '#4A5468' };
export const KIND_LABEL: Record<string, string> = { warmup: 'Échauffement', run: 'Course', fast: 'Accélération', recovery: 'Récupération', cooldown: 'Retour au calme' };
export const RPE_LABEL = ['', 'Très facile', 'Facile', 'Modéré', 'Difficile', 'Très dur'];

export const factor = (t: LoadType) => (t === 'two_dumbbells' ? 2 : t === 'one_dumbbell' ? 1 : 0);

export function flatSteps(segments: Segment[]): Step[] {
    const out: Step[] = [];
    segments.forEach((s) => {
        if (s.kind === 'repeat') {
            for (let i = 0; i < s.repeat_count; i++) s.children.forEach((st) => out.push({ ...st, cycle: i + 1, of: s.repeat_count }));
        } else out.push(s);
    });
    return out;
}
export const runMinutes = (segs: Segment[]) => flatSteps(segs).reduce((a, s) => a + (+s.duration_min || 0), 0);
export const estRunKm = (segs: Segment[]) => flatSteps(segs).reduce((a, s) => a + (s.duration_min / 60) * ((+s.speed_min_kmh + +s.speed_max_kmh) / 2), 0);
export function plannedMinutes(w: Workout): number {
    return w.type === 'run' ? runMinutes(w.segments) : w.estimated_duration_min || 0;
}
export function durationLabel(w: Workout): string {
    if (w.type === 'rest') return '–';
    if (w.type === 'recovery') return 'Facultatif · ~' + (w.estimated_duration_min || 20) + ' min';
    return plannedMinutes(w) + ' min';
}
const kmh = (v: number) => fmt(v, v % 1 ? 1 : 0);
export function speedLabel(s: Step): string {
    return (+s.speed_min_kmh === +s.speed_max_kmh ? kmh(s.speed_min_kmh) : kmh(s.speed_min_kmh) + '–' + kmh(s.speed_max_kmh)) + ' km/h';
}
export function repsLabel(min: number, max: number, ex: Pick<Exercise, 'measurement' | 'is_unilateral'>): string {
    const r = min === max ? String(min) : min + '–' + max;
    return r + (ex.measurement === 'time' ? ' s' : '') + (ex.is_unilateral ? ' / côté' : '');
}
export function kgLabel(kg: number | null, ex: Pick<Exercise, 'load_type'>): string {
    if (ex.load_type === 'bodyweight' || !kg) return 'Poids du corps';
    return ex.load_type === 'two_dumbbells' ? '2 × ' + fmtKg(kg) + ' kg' : fmtKg(kg) + ' kg';
}
/** Règle 7 : charge max utilisable selon le matériel (0 = pas de contrôle). */
export function maxLoadFor(load: LoadType, p: Pick<Profile, 'dumbbell_max_kg' | 'dumbbells_combinable'>): number {
    if (load === 'bodyweight') return 0;
    if (load === 'two_dumbbells') return p.dumbbell_max_kg;
    return p.dumbbells_combinable ? p.dumbbell_max_kg * 2 : p.dumbbell_max_kg;
}
export function setVolume(s: SetRow, load: LoadType, unilateral: boolean): number {
    return s.is_completed ? s.weight_kg * s.reps * factor(load) * (unilateral ? 2 : 1) : 0;
}
export const itemVolume = (it: Pick<SessionItem, 'sets' | 'load_type' | 'is_unilateral'>) =>
    it.sets.reduce((a, s) => a + setVolume(s, it.load_type, it.is_unilateral), 0);
export function setsSummary(it: Pick<SessionItem, 'sets' | 'load_type' | 'measurement'>): string {
    const done = it.sets.filter((s) => s.is_completed !== false);
    if (!done.length) return '–';
    const unit = it.measurement === 'time' ? ' s' : '';
    if (it.load_type === 'bodyweight') return done.map((s) => s.reps + unit).join(' · ');
    if (done.every((s) => s.weight_kg === done[0].weight_kg)) return fmtKg(done[0].weight_kg) + ' kg × ' + done.map((s) => s.reps).join(', ');
    return done.map((s) => fmtKg(s.weight_kg) + '×' + s.reps).join(' · ');
}
export const exerciseById = (list: Exercise[], id: number | null) => list.find((e) => e.id === id);
export const totalSets = (ex: WorkoutExercise[]) => ex.reduce((a, e) => a + (+e.sets || 0), 0);
