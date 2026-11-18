@props(['action', 'method' => 'POST', 'exercise' => null, 'category', 'candidates' => [], 'selected' => null, 'set' => null, 'nextNumber' => 1, 'showStep' => true, 'bodyWeight' => null, 'bodyWeightOn' => false, 'submit' => '記録する'])

@php
    // 初期値：目標RIR＝1セット目は推奨範囲の大きい方、2セット目以降は小さい方。実績RIRは目標RIRと同じ。レップ数は推奨レップ数の最小値
    $defaultTarget = $set?->target_rir ?? ($nextNumber === 1 ? $category->rir_max : $category->rir_min);
    $config = [
        'candidates' => $candidates,
        'selected' => $selected,
        'steps' => config('trainingmax.weight_steps'),
        'defaultStep' => 2.5,
        'minReps' => $category->rep_min,
        'weight' => old('weight', $set ? (float) $set->weight : ''),
        'reps' => (int) old('reps', $set?->reps ?? $category->rep_min),
        'targetRir' => (int) old('target_rir', $defaultTarget),
        'actualRir' => (int) old('actual_rir', $set?->actual_rir ?? $defaultTarget),
        'actualTouched' => $set !== null || old('actual_rir') !== null,
        'bodyWeight' => $bodyWeight['weight'] ?? null,
    ];
@endphp

<form method="POST" action="{{ $action }}" x-data="setForm(@js($config))" class="rounded-xl bg-white p-4 shadow">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($exercise)
        <input type="hidden" name="exercise_id" value="{{ $exercise->id }}">
    @endif

    @if ($exercise)
        @error('exercise_id')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror

        @if (count($candidates) > 0)
            <div class="mb-3">
                <p class="mb-1 text-sm font-medium">推奨重量を選んでください</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="candidate in candidates" :key="candidate.key">
                        <button type="button" @click="choose(candidate)"
                                :class="selected === candidate.key ? 'border-blue-600 bg-blue-50' : 'border-gray-300'"
                                class="rounded-md border px-3 py-2 text-sm">
                            <span x-text="optionText(candidate)"></span>
                            <span x-show="candidate.reference" class="text-xs text-gray-500">（参考）</span>
                        </button>
                    </template>
                </div>
                <p class="mt-2 text-sm text-blue-800" x-show="recommendedText() !== ''" x-text="recommendedText()"></p>
            </div>
        @else
            <p class="mb-3 text-sm text-gray-600">初回のため推奨重量はありません。</p>
        @endif
    @endif

    {{-- 重量の刻みは、その種目の1セット目だけ選べる（同じ種目で2セット目以降に変わることはないため）。選んだ刻みはブラウザに記憶され、以降も使われる --}}
    @if ($showStep)
        <div class="mb-4">
            <label for="weight_step_select" class="mb-1 block text-sm font-medium">重量の刻み（kg）</label>
            <select id="weight_step_select" x-model.number="step" @change="onStepChange()" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                @foreach (config('trainingmax.weight_steps') as $step)
                    <option value="{{ $step }}">{{ $step }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">「−」「＋」の増減と推奨重量に使います。</p>
        </div>
    @endif

    <div class="mb-4">
        <label for="weight" class="mb-1 block text-sm font-medium">重量（kg）</label>
        <div class="flex items-stretch gap-2">
            <button type="button" @click="changeWeight(-1)" class="w-12 rounded-md border text-lg" aria-label="重量を減らす">−</button>
            <input id="weight" name="weight" type="number" inputmode="decimal" step="0.01" min="0.5" max="500" required
                   x-model="weight" @input="onWeightInput()"
                   class="block w-full rounded-md border px-3 py-2 text-center text-base {{ $errors->has('weight') ? 'border-red-500' : 'border-gray-300' }}">
            <button type="button" @click="changeWeight(1)" class="w-12 rounded-md border text-lg" aria-label="重量を増やす">＋</button>
            {{-- 自重ボタン：直近の体重（セッション日以前で最新の体組成の体重）を入れる。体重の記録がなければ押せない --}}
            @if ($bodyWeight)
                <button type="button" @click="toggleBodyWeight()" :aria-pressed="bodyWeightOn"
                        :class="bodyWeightOn ? 'border-blue-600 bg-blue-50 text-blue-800' : ''"
                        class="rounded-md border px-3 text-sm" aria-label="自重">自重</button>
            @else
                <button type="button" disabled title="体組成で体重を登録してください" class="rounded-md border px-3 text-sm text-gray-400">自重</button>
            @endif
        </div>
        @if ($bodyWeight)
            <p class="mt-1 text-xs text-gray-500">
                自重で行う種目は「自重」を押すと体重（{{ \App\Support\Format::weight($bodyWeight['weight']) }}・{{ $bodyWeight['measured_on']->isoFormat('YYYY/MM/DD') }}の記録）が入ります。プレート等の加重分は「＋」や直接入力で足してください。もう一度押すと元の値に戻ります。
            </p>
        @else
            <p class="mt-1 text-xs text-gray-500">自重で行う種目は、自重＋プレート等の合計を入力します。体組成で体重を登録してください（登録すると「自重」ボタンが使えます）。</p>
        @endif
        @error('weight')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mb-4">
        <div class="mb-1 flex items-center"><label for="reps" class="block text-sm font-medium">レップ数</label><x-term key="rep" /></div>
        <div class="flex items-stretch gap-2">
            <button type="button" @click="changeReps(-1)" class="w-12 rounded-md border text-lg" aria-label="レップ数を減らす">−</button>
            <input id="reps" name="reps" type="number" inputmode="numeric" step="1" min="1" max="100" required x-model="reps"
                   class="block w-full rounded-md border px-3 py-2 text-center text-base {{ $errors->has('reps') ? 'border-red-500' : 'border-gray-300' }}">
            <button type="button" @click="changeReps(1)" class="w-12 rounded-md border text-lg" aria-label="レップ数を増やす">＋</button>
        </div>
        @error('reps')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3">
        <div>
            <div class="mb-1 flex items-center"><label for="target_rir" class="block text-sm font-medium">目標RIR</label><x-term key="rir" /></div>
            <select id="target_rir" name="target_rir" x-model.number="targetRir" @change="onTargetRirChange()"
                    class="block w-full rounded-md border px-3 py-2 text-base {{ $errors->has('target_rir') ? 'border-red-500' : 'border-gray-300' }}">
                @for ($rir = 0; $rir <= 10; $rir++)
                    <option value="{{ $rir }}">{{ $rir }}</option>
                @endfor
            </select>
            @error('target_rir')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="mb-1 flex items-center"><label for="actual_rir" class="block text-sm font-medium">実績RIR</label><x-term key="rir" /></div>
            <select id="actual_rir" name="actual_rir" x-model.number="actualRir" @change="actualTouched = true"
                    class="block w-full rounded-md border px-3 py-2 text-base {{ $errors->has('actual_rir') ? 'border-red-500' : 'border-gray-300' }}">
                @for ($rir = 0; $rir <= 10; $rir++)
                    <option value="{{ $rir }}">{{ $rir }}</option>
                @endfor
            </select>
            @error('actual_rir')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <x-form.button>{{ $submit }}</x-form.button>
</form>
