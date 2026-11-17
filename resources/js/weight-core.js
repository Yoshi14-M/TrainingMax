/**
 * 重量欄の「自重」ボタンのロジック（DOM を使わない純粋な関数。`npm test` でテストする）。
 *
 * 自重で行う種目（懸垂・ディップス等）は、自重＋プレート等の合計を重量として入力する。
 * ボタンを押すと直近の体重が重量欄に入り、加重分は「＋」ボタンや直接入力で足す。
 * もう一度押す（チェックを外す）と、押す前の値に戻る。ボタンの状態は保存しない（入力補助のみ）。
 */

/**
 * @param {{weight: number|string, before: number|string|null, on: boolean}} state 現在の重量欄の値・押す前の値・ボタンの状態
 * @param {number|null} bodyWeight 直近の体重（体組成の記録がなければ null）
 * @returns {{weight: number|string, before: number|string|null, on: boolean}} 切り替え後の状態
 */
export function toggleBodyWeight(state, bodyWeight) {
    // 体重の記録がなければ、押しても何も変わらない
    if (bodyWeight === null || bodyWeight === undefined) {
        return state;
    }

    if (!state.on) {
        return { weight: bodyWeight, before: state.weight, on: true };
    }

    return { weight: state.before ?? '', before: null, on: false };
}
