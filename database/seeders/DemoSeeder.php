<?php

namespace Database\Seeders;

use App\Models\BodyComposition;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * デモ環境（DEMO_MODE=true）用のデータ。開発用のテストユーザー（DevelopmentSeeder）を作り、
     * グラフが読めるように、約12週間分のトレーニング記録と体組成を足す。
     * デモ用アカウントはボタンでログインするため、パスワードは毎回ランダムにする（誰も知らない値）。
     * 日付は実行日からの相対値なので、起動のたびに「直近」の記録になる。
     */
    public function run(): void
    {
        config(['trainingmax.seed.dev_user_password' => Str::random(40)]);

        $this->call(DevelopmentSeeder::class);

        $trainer = User::query()->where('email', 'trainer1@example.com')->firstOrFail();
        $client = User::query()->where('email', 'client1@example.com')->firstOrFail();
        $personal = User::query()->where('email', 'personal@example.com')->firstOrFail();

        $this->clientHistory($client, $trainer);
        $this->clientBodyHistory($client, $trainer);
        $this->personalHistory($personal);
    }

    /**
     * 山田太郎：週2回（4日・3日おき）、12週間で少しずつ重量が伸びる記録。一部はトレーナーが入力。
     */
    private function clientHistory(User $client, User $trainer): void
    {
        for ($week = 12; $week >= 1; $week--) {
            $progress = 12 - $week; // 0（12週前）〜 11（1週前）

            $bench = 50 + floor($progress / 2) * 2.5;   // 50 → 62.5
            $squat = 65 + floor($progress / 2) * 5;     // 65 → 90
            $row = 40 + floor($progress / 3) * 2.5;     // 40 → 47.5

            // 胸・肩の日
            $this->session($client, $week * 7 + 1, $week % 3 === 0 ? $trainer : $client, [
                'ベンチプレス' => [[$bench, 8, 2, 2], [$bench, 8, 1, 1], [$bench, 7, 1, 0]],
                'フロントプレス' => [[25 + floor($progress / 4) * 2.5, 10, 2, 2], [25 + floor($progress / 4) * 2.5, 9, 1, 1]],
                'サイドレイズ' => [[6 + floor($progress / 6) * 1, 15, 0, 0], [6 + floor($progress / 6) * 1, 14, 0, 0]],
            ]);

            // 脚・背中の日
            $this->session($client, $week * 7 - 3, $client, [
                'スクワット' => [[$squat, 8, 2, 2], [$squat, 8, 1, 1], [$squat, 7, 1, 1]],
                'Tバーロウ' => [[$row, 8, 2, 2], [$row, 8, 1, 1]],
                'EZバーカール' => [[20 + floor($progress / 4) * 2.5, 12, 1, 1], [20 + floor($progress / 4) * 2.5, 11, 1, 0]],
            ]);
        }
    }

    /**
     * 山田太郎：週1回の体組成（体重が少しずつ下がり、筋肉量はわずかに増える）。
     */
    private function clientBodyHistory(User $client, User $trainer): void
    {
        for ($week = 12; $week >= 3; $week--) {
            $progress = 12 - $week;

            $this->bodyComposition($client, $week * 7 + 2, $week % 4 === 0 ? $trainer : $client, [
                'weight_kg' => round(73.6 - $progress * 0.25, 1),
                'body_fat_percent' => round(20.6 - $progress * 0.15, 1),
                'muscle_mass_kg' => round(54.0 + $progress * 0.08, 1),
            ]);
        }
    }

    /**
     * 個人利用者：直近3週間の記録（ジムに所属しない使い方の確認用）。
     */
    private function personalHistory(User $personal): void
    {
        foreach ([20, 16, 13, 9, 6, 2] as $index => $daysAgo) {
            $weight = 40 + floor($index / 2) * 2.5;

            $this->session($personal, $daysAgo, $personal, [
                'ベンチプレス' => [[$weight, 10, 2, 2], [$weight, 9, 1, 1]],
                'ケーブルプレスダウン' => [[15, 15, 0, 0], [15, 13, 0, 0]],
            ]);
        }

        $this->bodyComposition($personal, 20, $personal, ['weight_kg' => 62.4, 'body_fat_percent' => 16.8]);
        $this->bodyComposition($personal, 6, $personal, ['weight_kg' => 62.0, 'body_fat_percent' => 16.5]);
    }

    /**
     * 1日分のセッション（種目・セット）。その日の記録が既にあれば作らない（DevelopmentSeeder の記録を優先）。
     * 推定1RM・セットボリュームは WorkoutSet の保存時に計算される。
     *
     * @param  array<string, array<int, array{0: float|int, 1: int, 2: int, 3: int}>>  $exercises  [種目名 => [[重量, レップ, 目標RIR, 実績RIR], ...]]
     */
    private function session(User $client, int $daysAgo, User $author, array $exercises): void
    {
        $date = now()->subDays($daysAgo)->toDateString();

        if ($client->workoutSessions()->where('performed_on', $date)->exists()) {
            return;
        }

        $session = new WorkoutSession;
        $session->forceFill(['user_id' => $client->id, 'performed_on' => $date, 'created_by' => $author->id])->save();

        $order = 0;

        foreach ($exercises as $name => $sets) {
            $exercise = Exercise::query()->where('name', $name)->first();

            if ($exercise === null) {
                continue;
            }

            $sessionExercise = new SessionExercise;
            $sessionExercise->forceFill([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'sort_order' => ++$order,
            ])->save();

            foreach ($sets as $index => [$weight, $reps, $targetRir, $actualRir]) {
                $set = new WorkoutSet;
                $set->fill(['weight' => $weight, 'reps' => $reps, 'target_rir' => $targetRir, 'actual_rir' => $actualRir]);
                $set->forceFill([
                    'session_exercise_id' => $sessionExercise->id,
                    'set_number' => $index + 1,
                    'created_by' => $author->id,
                    'updated_by' => $author->id,
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, float>  $values
     */
    private function bodyComposition(User $client, int $daysAgo, User $author, array $values): void
    {
        $date = now()->subDays($daysAgo)->toDateString();

        if ($client->bodyCompositions()->where('measured_on', $date)->exists()) {
            return;
        }

        $record = new BodyComposition;
        $record->fill(['measured_on' => $date, ...$values]);
        $record->forceFill(['user_id' => $client->id, 'created_by' => $author->id, 'updated_by' => $author->id])->save();
    }
}
