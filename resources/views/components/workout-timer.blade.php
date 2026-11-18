@props(['restSeconds' => null, 'returnUrl' => null])

@php
    $timer = config('trainingmax.timer');
    $config = [
        'returnUrl' => $returnUrl,
        'restSeconds' => $restSeconds,
        'defaultSeconds' => $timer['default_seconds'],
        'min' => $timer['min_seconds'],
        'max' => $timer['max_seconds'],
        'adjustStep' => $timer['adjust_seconds'],
        'blinkSeconds' => $timer['blink_seconds'],
    ];
@endphp

{{-- 休憩タイマー（画面下部に固定）。セットの記録・編集で再読み込みされても、種目など他の画面へ移動しても、カウントダウンは続く --}}
<div x-data="workoutTimer(@js($config))" x-show="visible" x-cloak
     :class="blinking ? 'animate-pulse bg-yellow-100' : 'bg-white'"
     class="fixed inset-x-0 bottom-0 z-20 border-t shadow-[0_-2px_8px_rgba(0,0,0,0.08)]" aria-label="休憩タイマー">
    <div class="mx-auto max-w-3xl px-4 py-2">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs text-gray-600">
                    休憩タイマー<x-term key="interval" />
                    <span x-show="ended" class="font-semibold text-red-600">終了しました</span>
                    {{-- 記録画面以外で作動中のときは、記録画面へ戻るリンクを出す --}}
                    <a x-show="showReturn" :href="savedUrl" class="ml-2 text-blue-700 underline">記録画面へ</a>
                </p>
                <p class="text-3xl font-semibold tabular-nums" x-text="clock" aria-live="off"></p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="adjust(-1)" class="rounded-md border px-3 py-2 text-sm">−{{ $timer['adjust_seconds'] }}秒</button>
                <button type="button" @click="adjust(1)" class="rounded-md border px-3 py-2 text-sm">＋{{ $timer['adjust_seconds'] }}秒</button>
            </div>
        </div>

        <div class="mt-2 flex items-center gap-2">
            <button type="button" x-show="!running" @click="start()" class="flex-1 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">開始</button>
            <button type="button" x-show="running" @click="stop()" class="flex-1 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">停止</button>
            <button type="button" @click="reset()" class="rounded-md border px-3 py-2 text-sm">リセット</button>

            <label class="flex items-center gap-1 text-sm" x-show="!running">
                <input type="number" inputmode="numeric" step="1" min="{{ $timer['min_seconds'] }}" max="{{ $timer['max_seconds'] }}"
                       :value="seconds" @change="setSeconds($event.target.value); $event.target.value = seconds"
                       class="w-20 rounded-md border border-gray-300 px-2 py-2 text-right" aria-label="休憩時間（秒）">
                <span>秒</span>
            </label>
        </div>
    </div>
</div>
