import { adjustSeconds, clampSeconds, formatClock, remainingSeconds, restoreState, serializeState } from './timer-core';

/**
 * 休憩タイマー（機能設計書F-10）。
 * - 「開始」で終了時刻を sessionStorage に保存する。同じタブで画面が再読み込みされても、他の画面（種目など）へ
 *   移動しても、終了時刻が未来ならカウントダウンを続け、画面下部に表示する（トレーナーが利用者のトレーニング中に
 *   他の画面を見る場合も、残り時間が分かるようにするため）。停止・リセット・終了で削除する。
 *   sessionStorage を使うのは、タブを閉じれば消えるため（値は終了時刻などだけで、個人情報は入れない）。
 * - 終了時：音＋バイブ（対応端末のみ）＋点滅。ブラウザは操作なしでは音を鳴らせないため、「開始」の操作で音を有効にし、
 *   再読み込み後は画面の最初のタップで有効にする（タップがなければ点滅とバイブだけになる）。
 * - 作動中は Wake Lock で画面の消灯を防ぐ（対応ブラウザのみ）。
 */
const STORAGE_KEY = 'trainingmax.timer';

export default (config) => ({
    min: config.min,
    max: config.max,
    adjustStep: config.adjustStep,
    blinkSeconds: config.blinkSeconds,
    storageKey: STORAGE_KEY,
    returnUrl: config.returnUrl,
    savedUrl: null,
    preset: config.restSeconds,
    defaultSeconds: config.defaultSeconds,

    seconds: 0,
    endsAt: null,
    remaining: 0,
    running: false,
    ended: false,
    blinking: false,

    tickId: null,
    blinkId: null,
    audio: null,
    wakeLock: null,

    init() {
        this.seconds = this.preset ?? this.defaultSeconds;

        const state = restoreState(this.read(), Date.now());

        this.savedUrl = state?.url ?? null;

        if (state?.status === 'running') {
            this.endsAt = state.endsAt;
            this.remaining = state.remaining;
            this.running = true;
            this.startTick();
            this.requestWakeLock();
            // 再読み込みの後は、画面の最初のタップで音を有効にする
            document.addEventListener('pointerdown', () => this.unlockAudio(), { once: true });
        } else if (state?.status === 'ended') {
            // 画面を離れている間に終わった：音は鳴らさず、終了の状態で表示する
            this.ended = true;
            this.clear();
        }

        // 表示している間は、下のコンテンツがタイマーに隠れないよう、ページの下に余白を作る
        document.body.classList.toggle('pb-44', this.visible);
        this.$watch('visible', (visible) => document.body.classList.toggle('pb-44', visible));

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.running) {
                this.requestWakeLock();
            }
        });
    },

    /** セット記録の直後や種目を選んだときだけでなく、作動中・終了後も表示する */
    get visible() {
        return this.preset !== null || this.running || this.ended;
    },

    /** 記録画面以外でタイマーを見ているときは、記録画面へ戻るリンクを出す */
    get showReturn() {
        if (!this.running || this.savedUrl === null) {
            return false;
        }

        try {
            return new URL(this.savedUrl, window.location.origin).pathname !== window.location.pathname;
        } catch (e) {
            return false;
        }
    },

    get clock() {
        if (this.running) {
            return formatClock(this.remaining);
        }

        return formatClock(this.ended ? 0 : this.seconds);
    },

    start() {
        if (this.running) {
            return;
        }

        const total = clampSeconds(this.seconds, this.min, this.max) ?? this.defaultSeconds;

        this.unlockAudio();
        this.stopBlink();
        this.savedUrl = this.returnUrl;
        this.ended = false;
        this.seconds = total;
        this.endsAt = Date.now() + total * 1000;
        this.remaining = total;
        this.running = true;
        this.write(total);
        this.startTick();
        this.requestWakeLock();
    },

    /** 停止：カウントダウンを止める（残り時間は表示に残る。保存した終了時刻は削除する） */
    stop() {
        if (!this.running) {
            return;
        }

        this.seconds = clampSeconds(this.remaining, this.min, this.max) ?? this.seconds;
        this.running = false;
        this.endsAt = null;
        this.stopTick();
        this.clear();
        this.releaseWakeLock();
    },

    /** リセット：推奨休憩時間（なければ既定）に戻す */
    reset() {
        this.running = false;
        this.endsAt = null;
        this.ended = false;
        this.stopTick();
        this.stopBlink();
        this.clear();
        this.releaseWakeLock();
        this.seconds = this.preset ?? this.defaultSeconds;
    },

    /** 「−15秒」「＋15秒」：開始前・作動中のどちらでも調整できる */
    adjust(direction) {
        const delta = direction * this.adjustStep;

        if (!this.running) {
            this.seconds = adjustSeconds(this.seconds, delta, this.min, this.max);

            return;
        }

        const next = clampSeconds(this.remaining + delta, 1, this.max);

        this.endsAt = Date.now() + next * 1000;
        this.remaining = next;
        this.write(next);
    },

    /** 秒数の直接入力（10〜600秒。範囲外は範囲内に収め、数値でなければ元に戻す） */
    setSeconds(value) {
        if (this.running) {
            return;
        }

        this.seconds = clampSeconds(value, this.min, this.max) ?? this.seconds;
    },

    startTick() {
        this.stopTick();
        this.tickId = window.setInterval(() => this.tick(), 250);
    },

    stopTick() {
        if (this.tickId !== null) {
            window.clearInterval(this.tickId);
            this.tickId = null;
        }
    },

    tick() {
        this.remaining = remainingSeconds(this.endsAt, Date.now());

        if (this.remaining <= 0) {
            this.finish();
        }
    },

    /** 終了：音＋バイブ＋点滅で知らせる */
    finish() {
        this.running = false;
        this.endsAt = null;
        this.ended = true;
        this.stopTick();
        this.clear();
        this.releaseWakeLock();
        this.beep();

        try {
            window.navigator.vibrate?.([300, 150, 300, 150, 300]);
        } catch (e) {
            // バイブに対応していない端末（iPhone など）は音と点滅だけ
        }

        this.blinking = true;
        this.blinkId = window.setTimeout(() => this.stopBlink(), this.blinkSeconds * 1000);
    },

    stopBlink() {
        this.blinking = false;

        if (this.blinkId !== null) {
            window.clearTimeout(this.blinkId);
            this.blinkId = null;
        }
    },

    unlockAudio() {
        try {
            const Context = window.AudioContext ?? window.webkitAudioContext;

            if (Context !== undefined && this.audio === null) {
                this.audio = new Context();
            }

            if (this.audio?.state === 'suspended') {
                this.audio.resume();
            }
        } catch (e) {
            this.audio = null;
        }
    },

    beep() {
        if (this.audio === null || this.audio.state !== 'running') {
            return;
        }

        const now = this.audio.currentTime;

        [0, 0.35, 0.7].forEach((offset) => {
            const oscillator = this.audio.createOscillator();
            const gain = this.audio.createGain();

            oscillator.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, now + offset);
            gain.gain.exponentialRampToValueAtTime(0.4, now + offset + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.25);
            oscillator.connect(gain).connect(this.audio.destination);
            oscillator.start(now + offset);
            oscillator.stop(now + offset + 0.3);
        });
    },

    async requestWakeLock() {
        try {
            if ('wakeLock' in window.navigator && this.wakeLock === null) {
                this.wakeLock = await window.navigator.wakeLock.request('screen');
                this.wakeLock.addEventListener('release', () => {
                    this.wakeLock = null;
                });
            }
        } catch (e) {
            // 対応していない・許可されない場合は、画面が消灯することがある
        }
    },

    releaseWakeLock() {
        try {
            this.wakeLock?.release();
        } catch (e) {
            // 解放できなくても支障はない
        }

        this.wakeLock = null;
    },

    read() {
        try {
            return window.sessionStorage.getItem(this.storageKey);
        } catch (e) {
            return null;
        }
    },

    write(total) {
        try {
            window.sessionStorage.setItem(this.storageKey, serializeState(this.endsAt, total, this.savedUrl));
        } catch (e) {
            // 保存できなくてもカウントダウンは続けられる（再読み込みで止まる）
        }
    },

    clear() {
        try {
            window.sessionStorage.removeItem(this.storageKey);
        } catch (e) {
            // 削除できなくても支障はない
        }
    },
});
