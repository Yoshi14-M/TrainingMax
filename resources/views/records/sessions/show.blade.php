@php
    $title = $session->performed_on->isoFormat('YYYY/MM/DD（ddd）');
    $baseUrl = route('records.sessions.show', [$client, $session]);
    $totalSets = $sessionExercises->sum(fn ($se) => $se->sets->count());

    // 部位 → 種目の選択用データ（対象の利用者に見える種目だけ）
    $parts = $exercises->groupBy('body_part_id')->map(fn ($group) => $group->first()->bodyPart)->sortBy('sort_order')->values();
    $picker = [
        'exercises' => $exercises->map(fn ($exercise) => ['id' => $exercise->id, 'name' => $exercise->name, 'partId' => $exercise->body_part_id])->values(),
        'partId' => $selected?->body_part_id ?? '',
        'exerciseId' => $selected?->id ?? '',
        'baseUrl' => $baseUrl,
    ];
@endphp

<x-layouts.app :title="$title" :timer-rest-seconds="$selected?->category->rest_seconds" :timer-return-url="$baseUrl">
    {{-- 日付の横：この日の記録（セッション）の削除 --}}
    <x-slot:actions>
        <x-confirm-modal :action="route('records.sessions.destroy', [$client, $session])"
                         :message="'この日の記録（'.$sessionExercises->count().'種目・'.$totalSets.'セット）をすべて削除します。よろしいですか？'"
                         button="この日の記録を削除" confirm="すべて削除する" />
    </x-slot:actions>

    {{-- 前後のセッションへの移動 --}}
    <div class="mb-4 flex items-center justify-between text-sm">
        @if ($previousDate)
            <a class="text-blue-700 underline" href="{{ route('records.sessions.show', [$client, $previousDate]) }}">← {{ \Illuminate\Support\Carbon::parse($previousDate)->isoFormat('MM/DD（ddd）') }}</a>
        @else
            <span></span>
        @endif
        @if ($nextDate)
            <a class="text-blue-700 underline" href="{{ route('records.sessions.show', [$client, $nextDate]) }}">{{ \Illuminate\Support\Carbon::parse($nextDate)->isoFormat('MM/DD（ddd）') }} →</a>
        @endif
    </div>

    {{-- 実施済みの種目ごとのセット一覧 --}}
    @forelse ($sessionExercises as $sessionExercise)
        <section class="mb-6">
            {{-- 種目名と分類（左）／この種目の記録（セットごと）の削除（右端） --}}
            <div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <h2 class="text-base font-semibold">
                        <a href="{{ $baseUrl }}?exercise={{ $sessionExercise->exercise_id }}">{{ $sessionExercise->exercise->name }}</a>
                    </h2>
                    <span class="text-xs text-gray-600">{{ $sessionExercise->exercise->category->name }}</span>
                </div>
                <x-confirm-modal :action="route('records.session-exercises.destroy', [$client, $session, $sessionExercise])"
                                 :message="'「'.$sessionExercise->exercise->name.'」の記録（'.$sessionExercise->sets->count().'セット）をすべて削除します。よろしいですか？'"
                                 button="この種目を削除" confirm="すべて削除する" />
            </div>

            <div class="overflow-x-auto rounded-xl bg-white p-2 shadow">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th class="px-2 py-1">セット</th>
                            <th class="px-2 py-1">重量</th>
                            <th class="px-2 py-1">レップ</th>
                            <th class="px-2 py-1">RIR</th>
                            <th class="px-2 py-1">推定1RM</th>
                            <th class="px-2 py-1">ボリューム</th>
                            <th class="px-2 py-1"><span class="sr-only">操作</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessionExercise->sets as $set)
                            <tr id="set-{{ $set->id }}" class="scroll-mt-32 border-t">
                                <td class="px-2 py-2">{{ $set->set_number }}</td>
                                <td class="px-2 py-2"><x-weight :value="$set->weight" /></td>
                                <td class="px-2 py-2">{{ $set->reps }}</td>
                                <td class="px-2 py-2">{{ $set->target_rir === $set->actual_rir ? $set->target_rir : '目標'.$set->target_rir.' / 実績'.$set->actual_rir }}</td>
                                <td class="px-2 py-2">{{ \App\Support\Format::oneDecimal($set->estimated_1rm) }}</td>
                                <td class="px-2 py-2">{{ \App\Support\Format::oneDecimal($set->set_volume) }}</td>
                                {{-- セットの右端：編集と削除 --}}
                                <td class="px-2 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <a class="rounded-md border px-3 py-1 text-sm text-blue-700" href="{{ $baseUrl }}?exercise={{ $sessionExercise->exercise_id }}&edit={{ $set->id }}#set-{{ $set->id }}">編集</a>
                                        <x-confirm-modal :action="route('records.sets.destroy', [$client, $session, $set])"
                                                         :message="$sessionExercise->sets->count() === 1 ? 'このセットを削除します。種目のセットがなくなるため、この種目もセッションから消えます。' : 'このセットを削除します。後ろのセット番号は繰り上がります。'"
                                                         button="削除" />
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" class="px-2 pb-2">
                                    <x-author :creator="$set->creator" :updater="$set->updater" :owner="$client" />
                                </td>
                            </tr>
                            @if ($editingSet?->id === $set->id)
                                <tr>
                                    <td colspan="7" class="px-2 pb-3">
                                        <x-set-form :action="route('records.sets.update', [$client, $session, $set])" method="PUT"
                                                    :category="$sessionExercise->exercise->category" :set="$set" :show-step="false" submit="更新" />
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-1 text-right text-sm">総ボリューム：{{ \App\Support\Format::oneDecimal($sessionExercise->sets->sum('set_volume')) }}</p>
        </section>
    @empty
        <p class="mb-6 text-sm text-gray-600">まだ種目が記録されていません。下から種目を選んで記録を始めましょう。</p>
    @endforelse

    {{-- 種目の選択とセット入力 --}}
    <section class="mb-8">
        <h2 class="mb-2 text-base font-semibold">セットを記録する</h2>

        <div class="mb-4 rounded-xl bg-white p-4 shadow" x-data="exercisePicker(@js($picker))">
            <div class="mb-3">
                <label for="part" class="mb-1 block text-sm font-medium">部位</label>
                <select id="part" x-model="partId" @change="onPartChange()" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                    <option value="">選択してください</option>
                    @foreach ($parts as $part)
                        <option value="{{ $part->id }}">{{ $part->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="exercise" class="mb-1 block text-sm font-medium">種目</label>
                <select id="exercise" x-model="exerciseId" @change="onExerciseChange()" :disabled="partId === ''" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                    <option value="">選択してください</option>
                    <template x-for="option in options()" :key="option.id">
                        <option :value="option.id" x-text="option.name" :selected="String(option.id) === String(exerciseId)"></option>
                    </template>
                </select>
            </div>
        </div>

        @if ($selected)
            <div class="mb-4 rounded-xl bg-white p-4 shadow">
                <p class="font-medium">{{ $selected->name }}</p>
                <p class="mt-1 text-sm text-gray-700">
                    {{ $selected->category->name }}／推奨 {{ $selected->category->repsLabel() }}・{{ $selected->category->rirLabel() }}・休憩{{ $selected->category->restLabel() }}
                </p>
                <p class="mt-1 text-sm"><x-youtube-link :name="$selected->name" /></p>
            </div>

            @php($nextNumber = ($selectedSessionExercise?->sets->count() ?? 0) + 1)
            @php($initial = collect($candidates)->firstWhere('key', 'previous')['key'] ?? (count($candidates) === 1 ? $candidates[0]['key'] : null))
            <x-set-form :action="route('records.sets.store', [$client, $session])" :exercise="$selected" :category="$selected->category"
                        :candidates="$candidates" :selected="$initial" :next-number="$nextNumber" :show-step="$nextNumber === 1" />
        @endif
    </section>
</x-layouts.app>
