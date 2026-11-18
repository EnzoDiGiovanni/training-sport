<script setup lang="ts">
// Port du moteur SVG du prototype (drawChart) : barres, lignes (option escalier) et poids avec moyenne mobile.
// Choix : pas de Chart.js, le rendu reste identique au prototype et sans dépendance.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { fmt } from '@/composables/useFormat';

export type ChartPoint = {
    label: string;
    value: number;
    ma?: number;
    cur?: boolean;
};
const props = withDefaults(
    defineProps<{
        kind: 'bar' | 'line' | 'weight';
        data: ChartPoint[];
        unit: string;
        d?: number;
        h?: number;
        goal?: number | null;
        step?: boolean;
    }>(),
    {
        d: 1,
        h: 200,
        goal: null,
        step: false,
    },
);

const el = ref<HTMLElement>();
const W = ref(600);
let ro: ResizeObserver | undefined;
onMounted(() => {
    ro = new ResizeObserver(
        () => (W.value = Math.max(240, el.value?.clientWidth || 600)),
    );
    if (el.value) ro.observe(el.value);
});
onBeforeUnmount(() => ro?.disconnect());

function niceTicks(min: number, max: number, n = 4): number[] {
    if (min === max) {
        min -= 1;
        max += 1;
    }
    const step0 = (max - min) / (n - 1);
    const mag = Math.pow(10, Math.floor(Math.log10(step0)));
    const step =
        [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= step0) ||
        10 * mag;
    const lo = Math.floor(min / step) * step,
        hi = Math.ceil(max / step) * step;
    const out: number[] = [];
    for (let v = lo; v <= hi + step / 1e6; v += step) out.push(+v.toFixed(6));
    return out;
}

const pad = { l: 40, r: 14, t: 14, b: 26 };
const geo = computed(() => {
    const data = props.data,
        H = props.h,
        n = data.length;
    const vals = data
        .map((d) => d.value)
        .concat(props.kind === 'weight' ? data.map((d) => d.ma ?? d.value) : [])
        .concat(
            props.goal != null && props.kind !== 'line' ? [props.goal] : [],
        );
    let mn = Math.min(...vals),
        mx = Math.max(...vals);
    if (props.kind === 'bar') mn = 0;
    else {
        const sp = mx - mn || 1;
        mn -= sp * 0.15;
        mx += sp * 0.15;
        if (props.kind === 'weight' && props.goal != null)
            mn = Math.min(mn, props.goal - 0.3);
    }
    const ticks = niceTicks(mn, mx, 4);
    mn = ticks[0];
    mx = ticks[ticks.length - 1];
    const iw = W.value - pad.l - pad.r,
        ih = H - pad.t - pad.b;
    const y = (v: number) => pad.t + ih - ((v - mn) / (mx - mn)) * ih;
    const x = (i: number) =>
        props.kind === 'bar'
            ? pad.l + ((i + 0.5) * iw) / n
            : pad.l + (n === 1 ? iw / 2 : (i / (n - 1)) * iw);
    return {
        n,
        H,
        mn,
        mx,
        ticks,
        iw,
        ih,
        x,
        y,
        bw: props.kind === 'bar' ? Math.min(34, (iw / n) * 0.62) : 0,
    };
});

const svg = computed(() => {
    const { n, H, mn, mx, ticks, iw, ih, x, y, bw } = geo.value;
    const data = props.data,
        Wv = W.value;
    if (!n) return '';
    const tfmt = (v: number) => fmt(v, props.d > 0 && mx - mn < 5 ? 1 : 0);
    let s = `<svg width="${Wv}" height="${H}" viewBox="0 0 ${Wv} ${H}" role="img" aria-label="Graphique">`;
    ticks.forEach(
        (t) =>
            (s += `<line x1="${pad.l}" x2="${Wv - pad.r}" y1="${y(t)}" y2="${y(t)}" stroke="#222227" stroke-width="1"/><text x="${pad.l - 8}" y="${y(t) + 3.5}" text-anchor="end">${tfmt(t)}</text>`),
    );
    const idxs =
        n <= 8 ? data.map((_, i) => i) : [0, Math.floor((n - 1) / 2), n - 1];
    idxs.forEach(
        (i) =>
            (s += `<text x="${x(i)}" y="${H - 6}" text-anchor="${props.kind === 'bar' || n <= 8 ? 'middle' : i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}">${data[i].label}</text>`),
    );
    const g = props.goal;
    if (g != null && g >= mn && g <= mx)
        s += `<line x1="${pad.l}" x2="${Wv - pad.r}" y1="${y(g)}" y2="${y(g)}" stroke="var(--muted)" stroke-dasharray="3 4" stroke-width="1"/><text x="${Wv - pad.r}" y="${y(g) - 6}" text-anchor="end" style="fill:var(--muted)">objectif ${fmt(g, g % 1 ? 1 : 0)}</text>`;
    if (props.kind === 'bar') {
        data.forEach((d, i) => {
            const h0 = Math.max(0, y(mn) - y(d.value)),
                isLast = d.cur || i === n - 1;
            s += `<rect x="${x(i) - bw / 2}" y="${y(d.value)}" width="${bw}" height="${h0}" rx="4" fill="var(--accent)" opacity="${isLast ? 1 : 0.42}"/>`;
            if (n <= 10 && d.value > 0)
                s += `<text x="${x(i)}" y="${y(d.value) - 6}" text-anchor="middle" style="fill:${isLast ? 'var(--text)' : 'var(--faint)'}">${fmt(d.value, props.d && d.value % 1 ? props.d : 0)}</text>`;
        });
    } else {
        const series =
            props.kind === 'weight'
                ? data.map((d) => d.ma ?? d.value)
                : data.map((d) => d.value);
        let path = '';
        if (props.step)
            series.forEach(
                (v, i) =>
                    (path += i
                        ? `H${x(i).toFixed(1)}V${y(v).toFixed(1)}`
                        : `M${x(i).toFixed(1)} ${y(v).toFixed(1)}`),
            );
        else
            path = series
                .map(
                    (v, i) =>
                        (i ? 'L' : 'M') +
                        x(i).toFixed(1) +
                        ' ' +
                        y(v).toFixed(1),
                )
                .join(' ');
        const gid = 'g' + Math.random().toString(36).slice(2, 8);
        s += `<defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--accent)" stop-opacity=".16"/><stop offset="1" stop-color="var(--accent)" stop-opacity="0"/></linearGradient></defs>`;
        s += `<path d="${path} L${x(n - 1).toFixed(1)} ${y(mn)} L${x(0).toFixed(1)} ${y(mn)} Z" fill="url(#${gid})"/>`;
        if (props.kind === 'weight')
            data.forEach(
                (d, i) =>
                    (s += `<circle cx="${x(i)}" cy="${y(d.value)}" r="2.6" fill="var(--muted)" opacity=".7"/>`),
            );
        s += `<path d="${path}" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`;
        if (props.kind !== 'weight' && n <= 24)
            series.forEach(
                (v, i) =>
                    i < n - 1 &&
                    (s += `<circle cx="${x(i)}" cy="${y(v)}" r="2.4" fill="var(--bg)" stroke="var(--accent)" stroke-width="1.5"/>`),
            );
        s += `<circle cx="${x(n - 1)}" cy="${y(series[n - 1])}" r="4.5" fill="var(--accent)" stroke="var(--surface)" stroke-width="2"/>`;
    }
    if (hover.value != null) {
        const i = hover.value;
        s += `<line x1="${x(i)}" x2="${x(i)}" y1="${pad.t}" y2="${pad.t + ih}" stroke="var(--border-strong)"/>`;
        if (props.kind !== 'bar')
            s += `<circle cx="${x(i)}" cy="${y(data[i].value)}" r="4" fill="var(--text)"/>`;
    }
    return (
        s +
        `<rect x="${pad.l}" y="0" width="${iw}" height="${H}" fill="transparent"/></svg>`
    );
});

const hover = ref<number | null>(null);
function onMove(ev: PointerEvent) {
    const { n, iw } = geo.value;
    const r = (ev.currentTarget as HTMLElement).getBoundingClientRect();
    const px = ev.clientX - r.left;
    const i =
        props.kind === 'bar'
            ? Math.floor((px - pad.l) / (iw / n))
            : Math.round((px - pad.l) / (iw / Math.max(1, n - 1)));
    hover.value = Math.max(0, Math.min(n - 1, i));
}
const tip = computed(() => {
    if (hover.value == null) return null;
    const d = props.data[hover.value],
        { x, y } = geo.value;
    return {
        left: Math.max(60, Math.min(W.value - 60, x(hover.value))) + 'px',
        top: y(d.value) + 'px',
        d,
    };
});
</script>

<template>
    <div
        ref="el"
        class="chart"
        :style="{ height: h + 'px' }"
        @pointermove="onMove"
        @pointerdown="onMove"
        @pointerleave="hover = null"
    >
        <p v-if="!data.length" class="empty">Pas encore de données.</p>
        <template v-else>
            <div v-html="svg" />
            <div
                v-if="tip"
                class="tip"
                :style="{ left: tip.left, top: tip.top, opacity: 1 }"
            >
                <span class="faint">{{ tip.d.label }}</span> ·
                <b>{{ fmt(tip.d.value, d) }} {{ unit }}</b
                ><span
                    v-if="kind === 'weight' && tip.d.ma != null"
                    class="faint"
                >
                    · moy. {{ fmt(tip.d.ma, 1) }}</span
                >
            </div>
        </template>
    </div>
</template>
