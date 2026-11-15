/**
 * セット入力フォーム（S-11）。
 * - 推奨重量：サーバーから渡された「刻み×目標RIR」の表から選ぶだけ（計算式はPHP側の1か所だけ）
 * - 重量の刻み：0.5／1／1.25／2.5／5kg から選び、ブラウザに記憶する（DBには保存しない）
 * - 実績RIR：手で変えるまでは目標RIRに連動する
 */
const STEP_STORAGE_KEY = 'trainingmax.weightStep';

function loadStep(steps, fallback) {
    try {
        const saved = Number(window.localStorage.getItem(STEP_STORAGE_KEY));
        if (steps.includes(saved)) {
            return saved;
        }
    } catch (e) {
        // localStorage が使えない場合は既定値
    }

    return fallback;
}

function saveStep(step) {
    try {
        window.localStorage.setItem(STEP_STORAGE_KEY, String(step));
    } catch (e) {
        // 記憶できなくても入力は続けられる
    }
}

/** 重量の表示：必要な桁まで（62.5 / 61.25 / 60.0） */
export function formatWeight(value) {
    const rounded = Math.round(Number(value) * 100) / 100;
    const decimals = Math.abs(rounded * 10 - Math.round(rounded * 10)) > 0.0001 ? 2 : 1;

    return rounded.toFixed(decimals);
}

export default (config) => ({
    candidates: config.candidates,
    selected: config.selected,
    steps: config.steps,
    step: loadStep(config.steps, config.defaultStep),
    minReps: config.minReps,
    weight: config.weight,
    reps: config.reps,
    targetRir: config.targetRir,
    actualRir: config.actualRir,
    actualTouched: config.actualTouched,

    init() {
        if (this.selected !== null && (this.weight === '' || this.weight === null)) {
            this.applyRecommendation();
        }
    },

    candidate() {
        return this.candidates.find((c) => c.key === this.selected) ?? null;
    },

    recommended(candidate = this.candidate()) {
        if (candidate === null) {
            return null;
        }

        const value = candidate.table?.[String(this.step)]?.[Number(this.targetRir)];

        return value > 0 ? value : null;
    },

    recommendedText() {
        const value = this.recommended();

        return value === null ? '' : `推奨：${formatWeight(value)}kg × ${this.minReps}回（RIR ${this.targetRir}）`;
    },

    optionText(candidate) {
        const value = this.recommended(candidate);

        return value === null ? candidate.label : `${candidate.label} ${formatWeight(value)}kg`;
    },

    choose(candidate) {
        this.selected = candidate.key;
        this.applyRecommendation();
    },

    applyRecommendation() {
        const value = this.recommended();

        if (value !== null) {
            this.weight = value;
        }
    },

    /** 目標RIRを変えると、推奨重量を再計算して重量欄に入れ、実績RIRも（手で変えるまでは）連動させる */
    onTargetRirChange() {
        if (!this.actualTouched) {
            this.actualRir = this.targetRir;
        }
        this.applyRecommendation();
    },

    onStepChange() {
        saveStep(this.step);
        this.applyRecommendation();
    },

    /** 重量を手で入力したら、推奨重量の選択を外す */
    onWeightInput() {
        this.selected = null;
    },

    changeWeight(direction) {
        const next = (Number(this.weight) || 0) + direction * this.step;

        this.weight = Math.min(500, Math.max(0.5, Math.round(next * 100) / 100));
        this.selected = null;
    },

    changeReps(direction) {
        this.reps = Math.min(100, Math.max(1, (Number(this.reps) || 0) + direction));
    },
});
