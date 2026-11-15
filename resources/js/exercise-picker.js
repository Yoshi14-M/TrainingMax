/**
 * 部位 → 種目の選択（S-11）。部位を選ぶとその部位の種目だけが選択肢に出る。
 * 種目を選ぶと ?exercise=ID で再表示し、推奨重量などをサーバー側で計算して表示する。
 */
export default (config) => ({
    exercises: config.exercises,
    partId: config.partId,
    exerciseId: config.exerciseId,
    baseUrl: config.baseUrl,

    options() {
        return this.exercises.filter((exercise) => String(exercise.partId) === String(this.partId));
    },

    onPartChange() {
        this.exerciseId = '';
    },

    onExerciseChange() {
        if (this.exerciseId !== '') {
            window.location.href = `${this.baseUrl}?exercise=${encodeURIComponent(this.exerciseId)}`;
        }
    },
});
