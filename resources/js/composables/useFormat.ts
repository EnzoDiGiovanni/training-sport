// Formats français (virgule décimale, semaine du lundi), repris du prototype.
export const DAY_NAMES = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
export const DAY_SHORT = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
export const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
export const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

export function fmt(n: number | null | undefined, d = 1): string {
    if (n == null || isNaN(n)) return '–';
    return Number(n).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
}
/** Charge : décimale seulement si nécessaire (10 → « 10 », 7,5 → « 7,5 »). */
export const fmtKg = (n: number) => fmt(n, n % 1 ? 1 : 0);
export const fmtInt = (n: number) => Math.round(n).toLocaleString('fr-FR');
export function fmtDur(sec: number): string {
    const m = Math.round(sec / 60);
    if (m < 60) return m + ' min';
    return Math.floor(m / 60) + ' h ' + String(m % 60).padStart(2, '0');
}
export function fmtClock(sec: number): string {
    sec = Math.max(0, Math.round(sec));
    const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    return (h ? h + ':' + String(m).padStart(2, '0') : String(m).padStart(2, '0')) + ':' + String(s).padStart(2, '0');
}
/** « 30:00 », « 1:05:30 » ou « 30 » (minutes) → secondes ; NaN si invalide. */
export function parseClock(str: string | null | undefined): number {
    if (!str) return 0;
    const p = String(str).trim().split(':').map((x) => parseFloat(x.replace(',', '.')));
    if (p.some(isNaN)) return NaN;
    if (p.length === 1) return p[0] * 60;
    if (p.length === 2) return p[0] * 60 + p[1];
    return p[0] * 3600 + p[1] * 60 + p[2];
}
/** Nombre saisi en français (« 7,5 ») → number ; NaN si vide ou invalide. */
export function num(v: unknown): number {
    if (v === '' || v == null) return NaN;
    return parseFloat(String(v).replace(',', '.'));
}
export function parseIso(s: string): Date {
    const [y, m, d] = s.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
}
export function iso(d: Date): string {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
export const dayIdx = (d: Date) => (d.getDay() + 6) % 7;
export const toDate = (d: Date | string) => (typeof d === 'string' ? parseIso(d) : d);
export function dateLong(d: Date | string): string {
    const x = toDate(d);
    return DAY_NAMES[dayIdx(x)].toLowerCase() + ' ' + x.getDate() + ' ' + MONTHS[x.getMonth()];
}
export function dateShort(d: Date | string): string {
    const x = toDate(d);
    return x.getDate() + ' ' + MONTHS_SHORT[x.getMonth()];
}
export const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
export function daysAgo(isoStr: string): string {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const n = Math.round((today.getTime() - parseIso(isoStr).getTime()) / 864e5);
    return n === 0 ? 'aujourd’hui' : n === 1 ? 'hier' : 'il y a ' + n + ' j';
}
export function weekNumber(x: Date): number {
    const d = new Date(Date.UTC(x.getFullYear(), x.getMonth(), x.getDate()));
    const dn = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dn);
    const y = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil(((d.getTime() - y.getTime()) / 864e5 + 1) / 7);
}
export function addDays(d: Date, n: number): Date {
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
}
export function mondayOf(d: Date): Date {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return addDays(x, -dayIdx(x));
}
