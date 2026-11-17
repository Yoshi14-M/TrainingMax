import assert from 'node:assert/strict';
import { test } from 'node:test';
import { toggleBodyWeight } from '../../resources/js/weight-core.js';

test('自重ボタンを押すと直近の体重が重量欄に入り押す前の値を覚える', () => {
    assert.deepEqual(toggleBodyWeight({ weight: 20, before: null, on: false }, 70.5), { weight: 70.5, before: 20, on: true });
});

test('重量欄が空のときに押しても押す前の値として空を覚える', () => {
    assert.deepEqual(toggleBodyWeight({ weight: '', before: null, on: false }, 70), { weight: 70, before: '', on: true });
});

test('もう一度押すと押す前の値に戻る', () => {
    assert.deepEqual(toggleBodyWeight({ weight: 80, before: 20, on: true }, 70.5), { weight: 20, before: null, on: false });
    assert.deepEqual(toggleBodyWeight({ weight: 70.5, before: '', on: true }, 70.5), { weight: '', before: null, on: false });
});

test('加重分を足した後に外しても押す前の値に戻る', () => {
    const on = toggleBodyWeight({ weight: 15, before: null, on: false }, 70);
    const added = { ...on, weight: 80 };

    assert.deepEqual(toggleBodyWeight(added, 70), { weight: 15, before: null, on: false });
});

test('体重の記録がなければ押しても何も変わらない', () => {
    const state = { weight: 20, before: null, on: false };

    assert.deepEqual(toggleBodyWeight(state, null), state);
    assert.deepEqual(toggleBodyWeight(state, undefined), state);
});

test('押す前の値が無いまま戻す場合は空にする', () => {
    assert.deepEqual(toggleBodyWeight({ weight: 70, before: null, on: true }, 70), { weight: '', before: null, on: false });
});
