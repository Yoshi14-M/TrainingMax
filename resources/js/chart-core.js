/**
 * グラフの表示用の純粋な関数（DOM・Chart.js を使わない。`npm test` でテストする）。
 * 数値は画面に並べず、カーソルを合わせた（タップした）ときの吹き出しに表示するため、その整形をここで行う。
 */

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/** 横軸の目盛り：2026-11-01 → 11/1 */
export function shortDate(isoDate) {
    const [, month, day] = String(isoDate).split('-');

    return `${Number(month)}/${Number(day)}`;
}

/** 吹き出しの見出し：2026-11-01 → 2026/11/01（日） */
export function longDate(isoDate) {
    const [year, month, day] = String(isoDate).split('-').map(Number);
    const weekday = WEEKDAYS[new Date(Date.UTC(year, month - 1, day)).getUTCDay()];

    return `${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}（${weekday}）`;
}

/** セット数・種目数は整数、それ以外は小数第1位まで表示する */
export function formatValue(value, unit) {
    const number = Number(value);

    if (unit === 'セット' || unit === '種目') {
        return String(Math.round(number));
    }

    return number.toFixed(1);
}

/** 吹き出しの1行：「推定1RM：80.0kg」。値がない（その日に記録がない）指標は null を返して表示しない */
export function tooltipLine(label, value, unit) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return null;
    }

    return `${label}：${formatValue(value, unit)}${unit}`;
}
