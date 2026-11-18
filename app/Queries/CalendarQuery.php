<?php

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * ホーム（S-10）の月カレンダー：その月の実施日と部位、体組成を記録した日（機能設計書F-14）。
 * 日曜日始まりの週ごとに、前後の月の日を含めて返す。
 */
class CalendarQuery
{
    /**
     * 月の範囲：2000-01 〜 今月の1年後（セキュリティ設計書6.9）。
     */
    public const MIN_MONTH = '2000-01';

    /**
     * @return array{weeks: array<int, array<int, array<string, mixed>>>, previous: string|null, next: string|null}
     */
    public function handle(User $client, string $month): array
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month);
        $last = $first->endOfMonth();
        $today = CarbonImmutable::today();

        $sessions = $client->workoutSessions()
            ->whereBetween('performed_on', [$first->toDateString(), $last->toDateString()])
            ->with('sessionExercises.exercise.bodyPart')
            ->get()
            ->keyBy(fn ($session) => $session->performed_on->toDateString());

        $bodyDays = $client->bodyCompositions()
            ->whereBetween('measured_on', [$first->toDateString(), $last->toDateString()])
            ->pluck('measured_on')
            ->map(fn ($date) => $date->toDateString())
            ->flip();

        $days = [];

        for ($date = $first->startOfWeek(CarbonImmutable::SUNDAY); $date <= $last->endOfWeek(CarbonImmutable::SATURDAY); $date = $date->addDay()) {
            $key = $date->toDateString();
            $session = $sessions->get($key);

            $days[] = [
                'date' => $date,
                'inMonth' => $date->month === $first->month,
                'isToday' => $date->isSameDay($today),
                'session' => $session,
                // 部位名（表示順・重複なし）。複数ある場合はカンマ区切りで表示する
                'parts' => $session === null ? [] : $session->sessionExercises
                    ->map(fn ($sessionExercise) => $sessionExercise->exercise->bodyPart)
                    ->unique('id')
                    ->sortBy('sort_order')
                    ->pluck('name')
                    ->values()
                    ->all(),
                'hasBody' => $bodyDays->has($key),
                // 今日・過去日だけ作成できる（未来日は不可）
                'creatable' => $date <= $today,
            ];
        }

        $maxMonth = $today->addYear()->format('Y-m');

        return [
            'weeks' => array_chunk($days, 7),
            'previous' => $month > self::MIN_MONTH ? $first->subMonth()->format('Y-m') : null,
            'next' => $month < $maxMonth ? $first->addMonth()->format('Y-m') : null,
        ];
    }
}
