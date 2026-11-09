// Porté depuis docs/prototype.html (ILLOS, illoSvg). Silhouette de profil, 2 poses (A = départ, B = fin) animées en SMIL.
// Remplaçable plus tard par des images sous licence : il suffit de renseigner image_path sur l'exercice.
type Pt = [number, number];
type Pose = Record<string, Pt>;
type Illo = { props: any[]; db: 'both' | 'near' | 'single' | 'none'; A: Pose; B: Pose; fast?: boolean; still?: boolean };

const STAND: Pose = {H:[80,8],N:[80,15],P:[80,45],K1:[80,67],F1:[79,89],K2:[81,67],F2:[82,89]};
const SEAT: Pose = {H:[80,26],N:[80,34],P:[80,64],K1:[60,64],F1:[58,88],K2:[62,65],F2:[61,88]};

function P(base: Pose, over: Pose): Pose { return Object.assign({}, base, over); }

export const ILLOS: Record<string, Illo> = {
  bench: { // développé couché haltères
    props: [ {t:'bench', x:34, y:72, w:82}, ],
    db:'both',
    A:{H:[46,66],N:[54,68],P:[84,68],K1:[104,63],F1:[107,88],K2:[103,64],F2:[109,88],E1:[60,80],W1:[60,67],E2:[62,80],W2:[62,67]},
    B:{H:[46,66],N:[54,68],P:[84,68],K1:[104,63],F1:[107,88],K2:[103,64],F2:[109,88],E1:[54,55],W1:[54,41],E2:[56,55],W2:[56,41]}
  },
  row: { // rowing unilatéral sur banc
    props:[ {t:'bench', x:30, y:70, w:78} ],
    db:'near',
    A:{H:[41,43],N:[50,48],P:[80,46],K1:[76,67],F1:[79,89],K2:[84,68],F2:[104,68],E1:[52,60],W1:[52,73],E2:[48,59],W2:[46,70]},
    B:{H:[41,43],N:[50,48],P:[80,46],K1:[76,67],F1:[79,89],K2:[84,68],F2:[104,68],E1:[65,40],W1:[64,53],E2:[48,59],W2:[46,70]}
  },
  bulgarian: { // fentes bulgares
    props:[ {t:'bench', x:100, y:70, w:40} ],
    db:'both',
    A:{H:[80,9],N:[80,16],P:[80,46],K1:[66,66],F1:[58,89],K2:[90,67],F2:[108,68],E1:[82,31],W1:[82,46],E2:[79,31],W2:[79,46]},
    B:{H:[77,25],N:[78,32],P:[80,62],K1:[60,67],F1:[58,89],K2:[90,82],F2:[108,68],E1:[80,47],W1:[80,61],E2:[77,47],W2:[77,61]}
  },
  'shoulder-press': { // développé épaules assis
    props:[ {t:'bench', x:62, y:68, w:38} ],
    db:'both',
    A:P(SEAT,{E1:[77,46],W1:[75,32],E2:[83,46],W2:[85,32]}),
    B:P(SEAT,{E1:[78,20],W1:[77,6],E2:[82,20],W2:[83,6]})
  },
  crunch: { // crunch
    props:[ {t:'mat', x:30, y:90, w:96} ],
    db:'none',
    A:{H:[42,83],N:[50,86],P:[80,86],K1:[98,68],F1:[114,88],K2:[99,69],F2:[116,88],E1:[48,76],W1:[42,79],E2:[50,77],W2:[43,80]},
    B:{H:[51,62],N:[58,70],P:[80,86],K1:[98,68],F1:[114,88],K2:[99,69],F2:[116,88],E1:[59,57],W1:[51,60],E2:[61,58],W2:[52,61]}
  },
  rdl: { // soulevé de terre roumain
    props:[], db:'both',
    A:P(STAND,{E1:[80,30],W1:[78,46],E2:[81,30],W2:[80,46]}),
    B:{H:[53,35],N:[61,38],P:[91,46],K1:[83,67],F1:[80,89],K2:[84,67],F2:[82,89],E1:[61,52],W1:[61,66],E2:[63,52],W2:[63,66]}
  },
  incline: { // développé incliné
    props:[ {t:'incline', x1:84, y1:70, x2:48, y2:49, seatTo:104} ],
    db:'both',
    A:{H:[50,44],N:[57,49],P:[84,65],K1:[104,66],F1:[106,88],K2:[103,67],F2:[108,88],E1:[62,61],W1:[62,48],E2:[64,61],W2:[64,48]},
    B:{H:[50,44],N:[57,49],P:[84,65],K1:[104,66],F1:[106,88],K2:[103,67],F2:[108,88],E1:[57,36],W1:[57,22],E2:[59,36],W2:[59,22]}
  },
  'hip-thrust': { // hip thrust sur banc
    props:[ {t:'bench', x:16, y:66, w:44} ],
    db:'single',
    A:{H:[51,60],N:[58,64],P:[80,82],K1:[100,66],F1:[104,88],K2:[101,67],F2:[106,88],E1:[68,68],W1:[78,77],E2:[69,69],W2:[79,78]},
    B:{H:[51,60],N:[58,64],P:[86,63],K1:[105,63],F1:[104,88],K2:[106,64],F2:[106,88],E1:[70,57],W1:[84,58],E2:[71,58],W2:[85,59]}
  },
  curl: {
    props:[], db:'both',
    A:P(STAND,{E1:[80,31],W1:[79,46],E2:[82,31],W2:[82,46]}),
    B:P(STAND,{E1:[80,31],W1:[68,22],E2:[82,31],W2:[70,22]})
  },
  triceps: { // extension au-dessus de la tête (assis)
    props:[ {t:'bench', x:62, y:68, w:38} ],
    db:'single',
    A:P(SEAT,{E1:[80,20],W1:[91,31],E2:[81,20],W2:[92,31]}),
    B:P(SEAT,{E1:[80,20],W1:[80,6],E2:[81,20],W2:[81,6]})
  },
  'side-plank': { // gainage latéral (vue de face)
    props:[ {t:'mat', x:30, y:90, w:104} ],
    db:'none',
    A:{H:[44,58],N:[52,62],P:[84,79],K1:[104,84],F1:[124,88],K2:[104,83],F2:[124,87],E1:[52,88],W1:[42,89],E2:[64,62],W2:[80,74]},
    B:{H:[44,57],N:[52,62],P:[85,72],K1:[104,80],F1:[124,88],K2:[104,79],F2:[124,87],E1:[52,88],W1:[42,89],E2:[64,60],W2:[80,68]}
  },
  goblet: { // squat goblet
    props:[], db:'single',
    A:P(STAND,{E1:[76,29],W1:[72,22],E2:[77,30],W2:[73,23]}),
    B:{H:[77,31],N:[80,38],P:[92,66],K1:[68,70],F1:[78,89],K2:[69,71],F2:[80,89],E1:[72,48],W1:[68,42],E2:[73,49],W2:[69,43]}
  },
  pushup: { // pompes
    props:[ {t:'mat', x:30, y:90, w:104} ],
    db:'none',
    A:{H:[38,61],N:[46,64],P:[86,76],K1:[106,82],F1:[124,88],K2:[106,82],F2:[125,88],E1:[46,76],W1:[46,88],E2:[47,76],W2:[47,88]},
    B:{H:[38,77],N:[46,80],P:[86,84],K1:[106,86],F1:[124,88],K2:[106,86],F2:[125,88],E1:[57,73],W1:[46,88],E2:[58,73],W2:[47,88]}
  },
  run: { // course sur tapis
    props:[ {t:'treadmill'} ], db:'none', fast:true,
    A:{H:[76,10],N:[78,17],P:[82,45],K1:[70,60],F1:[66,81],K2:[92,62],F2:[104,70],E1:[88,30],W1:[86,42],E2:[72,28],W2:[64,22]},
    B:{H:[76,11],N:[78,18],P:[82,46],K1:[92,62],F1:[104,70],K2:[70,60],F2:[66,81],E1:[72,28],W1:[64,22],E2:[88,30],W2:[86,42]}
  },
  walk: { // récupération / mobilité
    props:[], db:'none',
    A:{H:[80,8],N:[80,15],P:[80,45],K1:[74,66],F1:[70,89],K2:[86,66],F2:[90,89],E1:[85,30],W1:[87,43],E2:[75,30],W2:[73,43]},
    B:{H:[80,8],N:[80,15],P:[80,45],K1:[86,66],F1:[90,89],K2:[74,66],F2:[70,89],E1:[75,30],W1:[73,43],E2:[85,30],W2:[87,43]}
  },
  custom: { // exercice personnalisé sans image
    props:[], db:'none', still:true,
    A:P(STAND,{E1:[72,28],W1:[64,20],E2:[88,28],W2:[96,20]}),
    B:P(STAND,{E1:[72,28],W1:[64,20],E2:[88,28],W2:[96,20]})
  }
};

const BONES: [string, string][] = [['N','P'],['N','E1'],['E1','W1'],['P','K1'],['K1','F1']];
const FAR_BONES: [string, string][] = [['N','E2'],['E2','W2'],['P','K2'],['K2','F2']];

function propSvg(p: any): string {
  const g = '#3A3A41', g2 = '#2C2C32';
  if(p.t==='bench'){
    const legH = 90 - p.y - 4;
    return `<rect x="${p.x}" y="${p.y}" width="${p.w}" height="5" rx="2.5" fill="${g}"/>
      <rect x="${p.x+6}" y="${p.y+5}" width="3" height="${legH}" fill="${g2}"/>
      <rect x="${p.x+p.w-9}" y="${p.y+5}" width="3" height="${legH}" fill="${g2}"/>`;
  }
  if(p.t==='incline'){
    return `<line x1="${p.x1}" y1="${p.y1}" x2="${p.x2}" y2="${p.y2}" stroke="${g}" stroke-width="5" stroke-linecap="round"/>
      <line x1="${p.x1}" y1="${p.y1}" x2="${p.seatTo}" y2="${p.y1}" stroke="${g}" stroke-width="5" stroke-linecap="round"/>
      <line x1="${p.x1}" y1="${p.y1+3}" x2="${p.x1}" y2="88" stroke="${g2}" stroke-width="3"/>
      <line x1="${(p.x1+p.x2)/2}" y1="${(p.y1+p.y2)/2+3}" x2="${p.x1-6}" y2="88" stroke="${g2}" stroke-width="2.5"/>`;
  }
  if(p.t==='mat'){
    return `<rect x="${p.x}" y="${p.y-1.5}" width="${p.w}" height="3" rx="1.5" fill="${g2}"/>`;
  }
  if(p.t==='treadmill'){
    return `<path d="M36 84 L132 84 L130 89 L40 89 Z" fill="${g}"/>
      <line x1="44" y1="84" x2="38" y2="40" stroke="${g}" stroke-width="3.5" stroke-linecap="round"/>
      <rect x="28" y="33" width="22" height="9" rx="2.5" fill="${g}"/>
      <line x1="40" y1="46" x2="62" y2="47" stroke="${g2}" stroke-width="2.5" stroke-linecap="round"/>`;
  }
  return '';
}

function anim(attr: string, a: number, b: number, dur: number): string {
  return `<animate attributeName="${attr}" values="${a};${b};${a}" keyTimes="0;0.5;1" calcMode="spline" keySplines=".45 0 .55 1;.45 0 .55 1" dur="${dur}s" repeatCount="indefinite"/>`;
}
function animT(a: Pt, b: Pt, dur: number): string {
  return `<animateTransform attributeName="transform" type="translate" values="${a[0]},${a[1]};${b[0]},${b[1]};${a[0]},${a[1]}" keyTimes="0;0.5;1" calcMode="spline" keySplines=".45 0 .55 1;.45 0 .55 1" dur="${dur}s" repeatCount="indefinite"/>`;
}

function boneSvg(A: Pose, B: Pose, j1: string, j2: string, stroke: string, width: number, opacity: number, animate: boolean, dur: number): string {
  const a1=A[j1], a2=A[j2], b1=B[j1], b2=B[j2];
  let inner = '';
  if(animate){
    inner = anim('x1',a1[0],b1[0],dur)+anim('y1',a1[1],b1[1],dur)+anim('x2',a2[0],b2[0],dur)+anim('y2',a2[1],b2[1],dur);
  }
  return `<line x1="${a1[0]}" y1="${a1[1]}" x2="${a2[0]}" y2="${a2[1]}" stroke="${stroke}" stroke-width="${width}" stroke-linecap="round" opacity="${opacity}">${inner}</line>`;
}

function dumbbell(A: Pose, B: Pose, j: string, animate: boolean, dur: number, big?: boolean): string {
  const a=A[j], b=B[j];
  const w = big ? 7 : 5.5;
  const shape = `<line x1="-${w}" y1="0" x2="${w}" y2="0" stroke="var(--accent)" stroke-width="1.6"/>
    <rect x="-${w+2}" y="-3.2" width="3.2" height="6.4" rx="1" fill="var(--accent)"/>
    <rect x="${w-1.2}" y="-3.2" width="3.2" height="6.4" rx="1" fill="var(--accent)"/>`;
  return `<g transform="translate(${a[0]},${a[1]})">${animate?animT(a,b,dur):''}${shape}</g>`;
}

export function illoSvg(key: string | null | undefined, opts: { animate?: boolean; pose?: 'A' | 'B'; label?: string } = {}): string {
  const d = (key && ILLOS[key]) || ILLOS.custom;
  let A = d.A, B = d.B;
  if(opts.pose==='B'){ A = d.B; B = d.B; }
  if(opts.pose==='A'){ B = d.A; }
  const reduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const anim = opts.animate !== false && !reduced && opts.pose===undefined && !d.still;
  const dur = d.fast ? 0.7 : 2.6;
  const txt = 'var(--text)';
  let s = `<svg viewBox="0 0 160 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${(opts.label||'Illustration du mouvement').replace(/"/g,'&quot;')}">`;
  // sol et grille de points
  s += `<defs><pattern id="dots" width="8" height="8" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r=".6" fill="#26262B"/></pattern></defs>`;
  s += `<rect x="0" y="0" width="160" height="100" fill="url(#dots)"/>`;
  s += `<line x1="0" y1="90.5" x2="160" y2="90.5" stroke="#2A2A30" stroke-width="1"/>`;
  s += `<g transform="translate(0,2)">`;
  d.props.forEach(p => s += propSvg(p));
  // membres éloignés
  FAR_BONES.forEach(([a,b]) => s += boneSvg(A,B,a,b,txt,3,.32,anim,dur));
  if(d.db==='both') s += dumbbell(A,B,'W2',anim,dur);
  // tronc + membres proches
  BONES.forEach(([a,b]) => s += boneSvg(A,B,a,b,txt,a==='N'&&b==='P'?4.2:3.2,1,anim,dur));
  // tête
  const h = A.H, hb = B.H;
  s += `<g transform="translate(${h[0]},${h[1]})">${anim?animT(h,hb,dur):''}<circle r="5.4" fill="${txt}"/></g>`;
  if(d.db==='both' || d.db==='near') s += dumbbell(A,B,'W1',anim,dur);
  if(d.db==='single') s += dumbbell(A,B,'W1',anim,dur,true);
  s += `</g></svg>`;
  return s;
}
