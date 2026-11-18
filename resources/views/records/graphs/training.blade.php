<x-layouts.app title="グラフ">
    @include('records.graphs._tabs', ['active' => 'training'])

    @if ($parts->isEmpty() && $part === null)
        <p class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow">トレーニングの記録がありません。記録するとグラフが表示されます。</p>
    @else
        {{-- 部位 →（種目）→ 期間。部位だけでも表示できる（種目を選ばなければ、その部位の合計） --}}
        <form method="GET" action="{{ route('records.graphs.training', $client) }}" class="mb-4 rounded-xl bg-white p-4 shadow"
              x-data="{ period: @js($period->key) }">
            <div class="mb-3">
                <label for="part" class="mb-1 block text-sm font-medium">部位</label>
                <select id="part" name="part" @change="$refs.exercise.value = ''; $el.form.submit()"
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                    @foreach ($parts as $option)
                        <option value="{{ $option->id }}" @selected($part?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="exercise" class="mb-1 block text-sm font-medium">種目</label>
                <select id="exercise" name="exercise" x-ref="exercise" @change="$el.form.submit()"
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                    <option value="">部位全体（セット数・種目数・総ボリューム）</option>
                    @foreach ($exercises as $option)
                        <option value="{{ $option->id }}" @selected($exercise?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            @include('records.graphs._period')

            <x-form.button>表示</x-form.button>
        </form>

        <h2 class="mb-2 text-base font-semibold">{{ $subject }}</h2>

        @include('records.graphs._chart', ['label' => $subject, 'terms' => ['estimated_1rm', 'total_volume', 'set_volume', 'rm']])
    @endif
</x-layouts.app>
