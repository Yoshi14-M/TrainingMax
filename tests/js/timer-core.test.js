import assert from 'node:assert/strict';
import { test } from 'node:test';
import { adjustSeconds, clampSeconds, formatClock, remainingSeconds, restoreState, serializeState } from '../../resources/js/timer-core.js';

const MIN = 10;
const MAX = 600;

test('秒数は10〜600秒の範囲に収められる', () => {
    assert.equal(clampSeconds(90, MIN, MAX), 90);
    assert.equal(clampSeconds(10, MIN, MAX), 10);
    assert.equal(clampSeconds(600, MIN, MAX), 600);
    assert.equal(clampSeconds(9, MIN, MAX), 10);
    assert.equal(clampSeconds(0, MIN, MAX), 10);
    assert.equal(clampSeconds(-5, MIN, MAX), 10);
    assert.equal(clampSeconds(601, MIN, MAX), 600);
    assert.equal(clampSeconds('120', MIN, MAX), 120);
    assert.equal(clampSeconds(89.6, MIN, MAX), 90);
});

test('数値でない入力は無効として扱う', () => {
    assert.equal(clampSeconds('', MIN, MAX), null);
    assert.equal(clampSeconds(null, MIN, MAX), null);
    assert.equal(clampSeconds('abc', MIN, MAX), null);
    assert.equal(clampSeconds(undefined, MIN, MAX), null);
    assert.equal(clampSeconds(NaN, MIN, MAX), null);
    assert.equal(clampSeconds(Infinity, MIN, MAX), null);
});

test('15秒ずつ調整でき範囲を超えない', () => {
    assert.equal(adjustSeconds(90, 15, MIN, MAX), 105);
    assert.equal(adjustSeconds(90, -15, MIN, MAX), 75);
    assert.equal(adjustSeconds(15, -15, MIN, MAX), 10);
    assert.equal(adjustSeconds(10, -15, MIN, MAX), 10);
    assert.equal(adjustSeconds(595, 15, MIN, MAX), 600);
});

test('残り秒数は終了時刻から切り上げて求め過ぎていれば0になる', () => {
    assert.equal(remainingSeconds(10_000, 0), 10);
    assert.equal(remainingSeconds(10_000, 500), 10);
    assert.equal(remainingSeconds(10_000, 9_001), 1);
    assert.equal(remainingSeconds(10_000, 10_000), 0);
    assert.equal(remainingSeconds(10_000, 20_000), 0);
});

test('表示は分:秒の形式になる', () => {
    assert.equal(formatClock(0), '0:00');
    assert.equal(formatClock(5), '0:05');
    assert.equal(formatClock(65), '1:05');
    assert.equal(formatClock(180), '3:00');
    assert.equal(formatClock(600), '10:00');
    assert.equal(formatClock(-3), '0:00');
});

test('保存した終了時刻が未来なら再読み込み後もカウントダウンを続ける', () => {
    const raw = serializeState(100_000, 90);

    assert.deepEqual(restoreState(raw, 40_000), { status: 'running', endsAt: 100_000, total: 90, url: null, remaining: 60 });
});

test('保存した終了時刻が過去なら終了済みとして復元する', () => {
    const raw = serializeState(100_000, 90);

    assert.deepEqual(restoreState(raw, 100_000), { status: 'ended', total: 90, url: null });
    assert.deepEqual(restoreState(raw, 200_000), { status: 'ended', total: 90, url: null });
});

test('保存された値がない・壊れている場合は復元しない', () => {
    assert.equal(restoreState(null, 0), null);
    assert.equal(restoreState(undefined, 0), null);
    assert.equal(restoreState('', 0), null);
    assert.equal(restoreState('not json', 0), null);
    assert.equal(restoreState('null', 0), null);
    assert.equal(restoreState('[]', 0), null);
    assert.equal(restoreState('{"endsAt":"x","total":90}', 0), null);
    assert.equal(restoreState('{"endsAt":100}', 0), null);
});

test('保存するのは終了時刻と秒数と記録画面のURLだけである', () => {
    assert.deepEqual(JSON.parse(serializeState(123, 45)), { endsAt: 123, total: 45, url: null });
    assert.deepEqual(JSON.parse(serializeState(123, 45, '/clients/5/sessions/2026-11-13')), { endsAt: 123, total: 45, url: '/clients/5/sessions/2026-11-13' });
});

test('他の画面へ移動しても保存したURLとともに復元され記録画面へ戻れる', () => {
    const raw = serializeState(100_000, 90, '/clients/5/sessions/2026-11-13');

    assert.equal(restoreState(raw, 10_000).url, '/clients/5/sessions/2026-11-13');
    assert.equal(restoreState(raw, 100_000).url, '/clients/5/sessions/2026-11-13');
});

test('URLが文字列でない場合は無視する', () => {
    assert.equal(restoreState('{"endsAt":100000,"total":90,"url":123}', 0).url, null);
    assert.equal(restoreState('{"endsAt":100000,"total":90}', 0).url, null);
});
