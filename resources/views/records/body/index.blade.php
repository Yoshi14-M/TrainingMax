@php
    use App\Models\BodyComposition;

    $indexUrl = route('records.body.index', $client);
    $fields = BodyComposition::MAIN_FIELDS;
    $girths = BodyComposition::GIRTH_FIELDS;
    $girthOpen = $errors->hasAny(array_keys($girths)) || collect(array_keys($girths))->contains(fn ($field) => filled(old($field, $editing?->{$field})));
@endphp

<x-layouts.app title="体組成">
    {{-- 記録・更新のフォーム。既に記録がある日付を選ぶと、その記録の編集になる --}}
    <section id="body-form" class="mb-8 scroll-mt-32">
        <form method="POST" action="{{ route('records.body.store', $client) }}" class="rounded-xl bg-white p-4 shadow"
              x-data="{ base: @js($indexUrl) }">
            @csrf

            <div class="mb-4">
                <label for="measured_on" class="mb-1 block text-sm font-medium">測定日</label>
                <input id="measured_on" name="measured_on" type="date" required min="2000-01-01" max="{{ now()->toDateString() }}"
                       value="{{ old('measured_on', $date) }}"
                       @change="if ($event.target.value) { window.location.href = base + '?date=' + encodeURIComponent($event.target.value) }"
                       class="block w-full rounded-md border px-3 py-2 text-base {{ $errors->has('measured_on') ? 'border-red-500' : 'border-gray-300' }}">
                @error('measured_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @if ($editing)
                    <p class="mt-1 text-xs text-gray-600">この日の記録があります。保存すると更新されます。</p>
                @endif
            </div>

            @error('measurements')
                <p class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
            @enderror

            @foreach ($fields as $field => $definition)
                <x-form.input :name="$field" :label="$definition['label'].'（'.$definition['unit'].'）'" type="number"
                              :value="$editing?->{$field}" :required="false" inputmode="decimal" step="0.1"
                              :min="BodyComposition::RANGES[$field][0]" :max="BodyComposition::RANGES[$field][1]" autocomplete="off" />
            @endforeach

            {{-- 周囲径は折りたたみ（体重などをすぐ入力できるようにする） --}}
            <details class="mb-4" @if ($girthOpen) open @endif>
                <summary class="cursor-pointer text-sm font-medium">周囲径を入力する</summary>
                <div class="mt-3">
                    <p class="mb-3 text-xs text-gray-600">周囲径<x-term key="girth" />は、体の各部位を一周した長さです。</p>
                    @foreach ($girths as $field => $definition)
                        <x-form.input :name="$field" :label="$definition['label'].'（'.$definition['unit'].'）'" type="number"
                                      :value="$editing?->{$field}" :required="false" inputmode="decimal" step="0.1"
                                      :min="BodyComposition::RANGES[$field][0]" :max="BodyComposition::RANGES[$field][1]" autocomplete="off" />
                    @endforeach
                </div>
            </details>

            <x-form.button>{{ $editing ? '更新する' : '記録する' }}</x-form.button>
        </form>
    </section>

    {{-- 履歴（新しい順） --}}
    <section>
        <h2 class="mb-2 text-base font-semibold">履歴</h2>

        @forelse ($history as $record)
            <div class="mb-2 rounded-xl bg-white p-4 shadow">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ $record->measured_on->isoFormat('YYYY/MM/DD（ddd）') }}</p>
                        <p class="mt-1 text-sm text-gray-700">
                            体重 {{ $record->weight_kg !== null ? \App\Support\Format::oneDecimal($record->weight_kg).'kg' : '－' }}／
                            体脂肪率 {{ $record->body_fat_percent !== null ? \App\Support\Format::oneDecimal($record->body_fat_percent).'%' : '－' }}／
                            筋肉量 {{ $record->muscle_mass_kg !== null ? \App\Support\Format::oneDecimal($record->muscle_mass_kg).'kg' : '－' }}
                        </p>
                        <p class="mt-1"><x-author :creator="$record->creator" :updater="$record->updater" :owner="$client" /></p>
                    </div>

                    {{-- 右端：編集と削除 --}}
                    <div class="flex items-center gap-2">
                        <a class="rounded-md border px-3 py-1 text-sm text-blue-700" href="{{ $indexUrl }}?date={{ $record->measured_on->toDateString() }}#body-form">編集</a>
                        <x-confirm-modal :action="route('records.body.destroy', [$client, $record])"
                                         :message="$record->measured_on->isoFormat('YYYY/MM/DD').'の体組成の記録を削除します。よろしいですか？'" button="削除" />
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600">体組成の記録はまだありません。</p>
        @endforelse

        <div class="mt-4">{{ $history->links() }}</div>
    </section>
</x-layouts.app>
