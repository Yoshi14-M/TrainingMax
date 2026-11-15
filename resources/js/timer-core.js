/**
 * 休憩タイマーの純粋なロジック（DOM・ブラウザAPIを使わない。`npm test` でテストする）。
 * 終了時刻（エポックミリ秒）を基準にカウントダウンするため、画面が再読み込みされても残り時間を復元できる。
 */

/** 秒数を許可される範囲（min〜max）に収める。数値でなければ null */
export function clampSeconds(value, min, max) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const number = Math.round(Number(value));

    if (!Number.isFinite(number)) {
        return null;
    }

    return Math.min(max, Math.max(min, number));
}

/** 「−15秒」「＋15秒」などの調整（範囲内に収める） */
export function adjustSeconds(seconds, delta, min, max) {
    return clampSeconds(seconds + delta, min, max);
}

/** 終了時刻までの残り秒数（切り上げ。過ぎていれば0） */
export function remainingSeconds(endsAt, now) {
    return Math.max(0, Math.ceil((endsAt - now) / 1000));
}

/** 表示用の「分:秒」（例：1:05、10:00） */
export function formatClock(seconds) {
    const total = Math.max(0, Math.floor(seconds));
    const minutes = Math.floor(total / 60);
    const rest = String(total % 60).padStart(2, '0');

    return `${minutes}:${rest}`;
}

/**
 * sessionStorage に保存する値。保存するのは終了時刻・開始時の秒数・記録画面のURLだけ（個人情報は入れない）。
 * URL は、他の画面でタイマーを見ているときに記録画面へ戻るリンクに使う。
 */
export function serializeState(endsAt, total, url = null) {
    return JSON.stringify({ endsAt, total, url });
}

/**
 * sessionStorage の値から状態を復元する。
 * - 終了時刻が未来：running（残り秒数つき。他の画面へ移動しても続く）
 * - 終了時刻が過去：ended（画面を離れている間に終わった。音は鳴らさない）
 * - 値がない・壊れている：null
 */
export function restoreState(raw, now) {
    if (typeof raw !== 'string') {
        return null;
    }

    let parsed;

    try {
        parsed = JSON.parse(raw);
    } catch (e) {
        return null;
    }

    if (parsed === null || typeof parsed !== 'object' || !Number.isFinite(parsed.endsAt) || !Number.isFinite(parsed.total)) {
        return null;
    }

    const url = typeof parsed.url === 'string' ? parsed.url : null;

    if (parsed.endsAt > now) {
        return { status: 'running', endsAt: parsed.endsAt, total: parsed.total, url, remaining: remainingSeconds(parsed.endsAt, now) };
    }

    return { status: 'ended', total: parsed.total, url };
}
