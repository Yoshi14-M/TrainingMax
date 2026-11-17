import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatValue, longDate, shortDate, tooltipLine } from '../../resources/js/chart-core.js';

test('横軸の目盛りは月/日で表示する', () => {
    assert.equal(shortDate('2026-11-01'), '11/1');
    assert.equal(shortDate('2026-01-09'), '1/9');
    assert.equal(shortDate('2026-12-31'), '12/31');
});

test('吹き出しの見出しは日付と曜日を表示する', () => {
    assert.equal(longDate('2026-11-01'), '2026/11/01（日）');
    assert.equal(longDate('2026-11-13'), '2026/11/13（金）');
    assert.equal(longDate('2000-01-01'), '2000/01/01（土）');
});

test('セット数と種目数は整数、それ以外は小数第1位まで表示する', () => {
    assert.equal(formatValue(12, 'セット'), '12');
    assert.equal(formatValue(3, '種目'), '3');
    assert.equal(formatValue(80, 'kg'), '80.0');
    assert.equal(formatValue(18.25, '%'), '18.3');
    assert.equal(formatValue(5000, ''), '5000.0');
});

test('吹き出しの1行は指標名と値と単位になる', () => {
    assert.equal(tooltipLine('推定1RM', 80, 'kg'), '推定1RM：80.0kg');
    assert.equal(tooltipLine('体脂肪率', 18.2, '%'), '体脂肪率：18.2%');
    assert.equal(tooltipLine('セット数', 12, 'セット'), 'セット数：12セット');
    assert.equal(tooltipLine('総ボリューム', 1470, ''), '総ボリューム：1470.0');
});

test('その日に記録がない指標は吹き出しに表示しない', () => {
    assert.equal(tooltipLine('体重', null, 'kg'), null);
    assert.equal(tooltipLine('体重', undefined, 'kg'), null);
    assert.equal(tooltipLine('体重', NaN, 'kg'), null);
});
