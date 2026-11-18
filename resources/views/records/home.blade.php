@php
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    $homeUrl = route('records.home', $client);
@endphp

<x-layouts.app title="ホーム">
    {{-- 月の移動 --}}
    <div class="mb-3 flex items-center justify-between">
        @if ($previousMonth)
            <a class="rounded-md border px-3 py-2 text-sm" href="{{ $homeUrl }}?month={{ $previousMonth }}" aria-label="前の月">←</a>
        @else
            <span class="w-12"></span>
        @endif

        <h2 class="text-lg font-semibold">{{ $monthLabel }}</h2>

        @if ($nextMonth)
            <a class="rounded-md border px-3 py-2 text-sm" href="{{ $homeUrl }}?month={{ $nextMonth }}" aria-label="次の月">→</a>
        @else
            <span class="w-12"></span>
        @endif
    </div>

    {{-- カレンダー：トレーニングした日は部位名、体組成を記録した日は●。日付をタップすると記録を開く（なければ作成）。 --}}
    <div class="overflow-hidden rounded-xl bg-white shadow">
        <div class="grid grid-cols-7 border-b text-center text-xs text-gray-600">
            @foreach ($weekdays as $index => $weekday)
                <div @class(['py-2', 'text-red-600' => $index === 0, 'text-blue-600' => $index === 6])>{{ $weekday }}</div>
            @endforeach
        </div>

        @foreach ($weeks as $week)
            <div class="grid grid-cols-7 border-b last:border-b-0">
                @foreach ($week as $day)
                    @php
                        $date = $day['date'];
                        $key = $date->toDateString();
                        $cell = 'block min-h-16 p-1 text-center text-sm';
                    @endphp

                    @if (! $day['inMonth'])
                        {{-- 前後の月の日は、見た目だけ（その月の記録は表示しない） --}}
                        <div class="min-h-16 bg-gray-50 p-1 text-center text-sm text-gray-300">{{ $date->day }}</div>
                    @else
                        @if ($day['session'])
                            <a href="{{ route('records.sessions.show', [$client, $key]) }}" @class([$cell, 'bg-blue-50', 'ring-2 ring-inset ring-blue-600' => $day['isToday']])>
                        @elseif ($day['creatable'])
                            <a href="{{ route('records.sessions.create', $client) }}?date={{ $key }}" title="この日の記録を作成" @class([$cell, 'ring-2 ring-inset ring-blue-600' => $day['isToday']])>
                        @else
                            <div @class([$cell, 'text-gray-400'])>
                        @endif

                            <span @class(['font-semibold text-blue-700' => $day['isToday']])>{{ $date->day }}</span>
                            @if ($day['parts'] !== [])
                                <span class="mt-1 block break-all text-[10px] leading-tight text-blue-900">{{ implode(',', $day['parts']) }}</span>
                            @endif
                            @if ($day['hasBody'])
                                <span class="mt-1 inline-block h-2 w-2 rounded-full bg-emerald-500" title="体組成を記録"></span>
                            @endif

                        @if ($day['session'] || $day['creatable'])
                            </a>
                        @else
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    <p class="mt-2 text-xs text-gray-500">
        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 align-middle"></span> は体組成を記録した日です。
    </p>

    {{-- 今日のトレーニング --}}
    <div class="mt-6">
        @if ($todaySession)
            <a href="{{ route('records.sessions.show', [$client, $todaySession]) }}"
               class="block w-full rounded-md bg-gray-900 px-4 py-3 text-center text-base font-semibold text-white hover:bg-gray-700">今日の記録を開く</a>
        @else
            <a href="{{ route('records.sessions.today', $client) }}"
               class="block w-full rounded-md bg-gray-900 px-4 py-3 text-center text-base font-semibold text-white hover:bg-gray-700">今日のトレーニングを始める</a>
        @endif
    </div>
</x-layouts.app>
