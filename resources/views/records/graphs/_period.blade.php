{{-- 期間の選択（全期間／直近30日／直近90日／期間指定） --}}
<div class="mb-3">
    <label for="period" class="mb-1 block text-sm font-medium">期間</label>
    <select id="period" name="period" x-model="period" @change="if (period !== 'custom') $el.form.submit()"
            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
        @foreach ($periodOptions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
</div>

<div x-show="period === 'custom'" x-cloak class="mb-3 grid grid-cols-2 gap-3">
    <div>
        <label for="from" class="mb-1 block text-sm font-medium">開始日</label>
        <input id="from" name="from" type="date" min="2000-01-01" max="{{ now()->toDateString() }}"
               value="{{ $period->key === 'custom' ? $period->from?->toDateString() : '' }}"
               class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
    </div>
    <div>
        <label for="to" class="mb-1 block text-sm font-medium">終了日</label>
        <input id="to" name="to" type="date" min="2000-01-01" max="{{ now()->toDateString() }}"
               value="{{ $period->key === 'custom' ? $period->to?->toDateString() : '' }}"
               class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
    </div>
</div>
