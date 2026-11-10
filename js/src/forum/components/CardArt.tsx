import Component, { type ComponentAttrs } from 'flarum/common/Component';

/**
 * The illustrated band across the top of each card design. Original vector
 * art, drawn here; colours come from the card's own custom properties
 * (less/cards.less), so light and dark each get a tuned palette. Text never
 * sits on the art: the note and names are on the card's paper below.
 */

interface Attrs extends ComponentAttrs {
  design: string;
}

const W = 320;
const H = 80;

const svg = (children: any) => (
  <svg className="KudosArt" viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
    {children}
  </svg>
);

const star = (x: number, y: number, s: number) =>
  `M${x} ${y - 4 * s} L${x + s} ${y - s} L${x + 4 * s} ${y} L${x + s} ${y + s} L${x} ${y + 4 * s} L${x - s} ${y + s} L${x - 4 * s} ${y} L${x - s} ${y - s} Z`;

const RAYS = Array.from({ length: 11 }, (_, i) => -75 + i * 15);
const STARS: [number, number, number][] = [
  [24, 18, 1.2],
  [70, 40, 0.8],
  [112, 14, 1.5],
  [150, 50, 0.9],
  [188, 22, 1.1],
  [226, 58, 0.7],
  [300, 16, 1.3],
  [44, 58, 0.7],
  [132, 64, 0.6],
];
const CONFETTI: [string, number, number, number, number, number][] = [
  ['c', 22, 20, 4, 0, 0],
  ['r', 58, 52, 9, 25, 1],
  ['t', 96, 18, 10, -15, 2],
  ['c', 130, 60, 5, 0, 1],
  ['r', 162, 26, 9, -35, 0],
  ['t', 198, 58, 9, 20, 1],
  ['c', 232, 16, 4, 0, 2],
  ['r', 262, 50, 10, 60, 2],
  ['t', 298, 24, 9, 5, 0],
  ['c', 306, 64, 3, 0, 1],
  ['c', 80, 70, 3, 0, 2],
  ['r', 12, 66, 7, -20, 2],
];

function art(design: string) {
  switch (design) {
    case 'sunrise':
      return svg(
        <g>
          <g className="KudosArt-soft">
            {RAYS.map((a) => (
              <rect x="-2.5" y="-96" width="5" height="34" rx="2.5" transform={`translate(236 ${H + 6}) rotate(${a})`} />
            ))}
          </g>
          <circle className="KudosArt-main" cx="236" cy={H + 6} r="44" />
          <circle className="KudosArt-accent" cx="236" cy={H + 6} r="28" />
          <path
            className="KudosArt-hill"
            d={`M0 ${H - 10} C 50 ${H - 26}, 110 ${H - 22}, 160 ${H - 8} C 200 ${H + 2}, 260 ${H - 18}, 320 ${H - 12} L320 ${H} L0 ${H} Z`}
          />
          <path className="KudosArt-bird" d="M60 26 q6 -6 12 0 q6 -6 12 0 M96 38 q4 -4 8 0 q4 -4 8 0" />
        </g>
      );
    case 'meadow':
      return svg(
        <g>
          <path
            className="KudosArt-soft"
            d={`M0 ${H - 18} C 70 ${H - 40}, 150 ${H - 34}, 210 ${H - 20} C 260 ${H - 8}, 300 ${H - 26}, 320 ${H - 30} L320 ${H} L0 ${H} Z`}
          />
          <path className="KudosArt-hill" d={`M0 ${H - 6} C 90 ${H - 22}, 180 ${H - 12}, 320 ${H - 16} L320 ${H} L0 ${H} Z`} />
          <g className="KudosArt-stem">
            <path d="M262 80 C 262 56, 270 34, 290 18" />
            <path d="M72 80 C 72 66, 66 56, 56 48" />
          </g>
          <g className="KudosArt-main">
            <path d="M266 56 C 248 50, 240 36, 242 26 C 256 30, 266 42, 266 56 Z" />
            <path d="M276 36 C 290 26, 306 26, 312 30 C 306 42, 290 44, 276 36 Z" />
            <path d="M286 22 C 280 10, 282 0, 288 -4 C 296 6, 296 16, 286 22 Z" />
            <path d="M68 62 C 56 60, 48 50, 48 42 C 60 44, 68 52, 68 62 Z" />
          </g>
          <g className="KudosArt-accent">
            <circle cx="150" cy="56" r="6" />
            <circle cx="192" cy="62" r="4.5" />
          </g>
          <g className="KudosArt-dot">
            <circle cx="150" cy="56" r="2.4" />
            <circle cx="192" cy="62" r="1.8" />
          </g>
        </g>
      );
    case 'tide':
      return svg(
        <g>
          <circle className="KudosArt-accent" cx="250" cy="28" r="14" />
          <path className="KudosArt-bird" d="M96 22 q5 -5 10 0 q5 -5 10 0 M128 32 q4 -4 8 0 q4 -4 8 0" />
          <path
            className="KudosArt-soft"
            d={`M0 ${H - 26} C 40 ${H - 40}, 80 ${H - 40}, 120 ${H - 26} S 200 ${H - 12}, 240 ${H - 26} S 300 ${H - 40}, 320 ${H - 32} L320 ${H} L0 ${H} Z`}
          />
          <path
            className="KudosArt-main"
            d={`M0 ${H - 12} C 40 ${H - 24}, 80 ${H - 24}, 120 ${H - 12} S 200 ${H}, 240 ${H - 12} S 300 ${H - 24}, 320 ${H - 18} L320 ${H} L0 ${H} Z`}
          />
          <path className="KudosArt-foam" d={`M30 ${H - 20} q8 -5 16 0 M180 ${H - 10} q8 -5 16 0`} />
        </g>
      );
    case 'confetti':
      return svg(
        <g>
          {CONFETTI.map(([kind, x, y, s, r, c]) => {
            const cls = ['KudosArt-main', 'KudosArt-accent', 'KudosArt-third'][c];
            if (kind === 'c') return <circle className={cls} cx={x} cy={y} r={s} />;
            if (kind === 'r')
              return <rect className={cls} x={x - s / 2} y={y - s / 4} width={s} height={s / 2} rx="1.5" transform={`rotate(${r} ${x} ${y})`} />;
            return (
              <path
                className={cls}
                d={`M${x} ${y - s / 2} L${x + s / 2} ${y + s / 2} L${x - s / 2} ${y + s / 2} Z`}
                transform={`rotate(${r} ${x} ${y})`}
              />
            );
          })}
          <path className="KudosArt-squiggle" d="M206 34 q 7 -9 14 0 t 14 0 t 14 0" />
          <path className="KudosArt-squiggle" d="M30 40 q 6 -8 12 0 t 12 0" />
        </g>
      );
    case 'starlight':
      return svg(
        <g>
          {STARS.map(([x, y, s]) => (
            <path className="KudosArt-accent" d={star(x, y, s)} />
          ))}
          <path className="KudosArt-main" d="M282 56 a 26 26 0 1 1 -20 -42 a 20 20 0 1 0 20 42 Z" />
          <path
            className="KudosArt-soft"
            d={`M0 ${H - 6} C 70 ${H - 18}, 150 ${H - 10}, 220 ${H - 18} S 300 ${H - 20}, 320 ${H - 14} L320 ${H} L0 ${H} Z`}
          />
        </g>
      );
    default:
      // letterpress: a stamp with a heart, a postmark and the airmail stripe.
      return svg(
        <g>
          <g className="KudosArt-stripe">
            {Array.from({ length: 22 }, (_, i) => (
              <rect x={i * 16 - 4} y={H - 6} width="8" height="6" transform={`skewX(-30)`} className={i % 2 ? 'KudosArt-main' : 'KudosArt-third'} />
            ))}
          </g>
          <g className="KudosArt-ring">
            <circle cx="214" cy="38" r="20" />
            <path d="M178 30 q 9 -5 18 0 t 18 0 t 18 0 t 18 0 M178 44 q 9 -5 18 0 t 18 0 t 18 0 t 18 0" />
          </g>
          <g transform="translate(254 8) rotate(5)">
            <rect className="KudosArt-paper" x="0" y="0" width="46" height="56" rx="2" />
            <rect className="KudosArt-perf" x="4" y="4" width="38" height="48" />
            <path className="KudosArt-main" d="M23 44 C 11 35, 7 29, 10 23 C 13 17, 20 17, 23 23 C 26 17, 33 17, 36 23 C 39 29, 35 35, 23 44 Z" />
          </g>
        </g>
      );
  }
}

export default class CardArt extends Component<Attrs> {
  view() {
    return art(this.attrs.design);
  }
}
