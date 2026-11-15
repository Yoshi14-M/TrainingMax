@props(['action', 'method' => 'POST', 'exercise' => null, 'parts', 'categories', 'submit' => '登録'])

<form method="POST" action="{{ $action }}" {{ $attributes->class('rounded-xl bg-white p-4 shadow') }}>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="mb-4">
        <label for="body_part_id" class="mb-1 block text-sm font-medium">部位</label>
        <select id="body_part_id" name="body_part_id" required class="block w-full rounded-md border px-3 py-2 text-base {{ $errors->has('body_part_id') ? 'border-red-500' : 'border-gray-300' }}">
            <option value="">選択してください</option>
            @foreach ($parts as $part)
                <option value="{{ $part->id }}" @selected((string) old('body_part_id', $exercise?->body_part_id) === (string) $part->id)>{{ $part->name }}</option>
            @endforeach
        </select>
        @error('body_part_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <x-form.input name="name" label="種目名" :value="$exercise?->name" maxlength="50" autocomplete="off" />

    <div class="mb-4">
        <label for="exercise_category_id" class="mb-1 block text-sm font-medium">分類</label>
        <select id="exercise_category_id" name="exercise_category_id" required class="block w-full rounded-md border px-3 py-2 text-base {{ $errors->has('exercise_category_id') ? 'border-red-500' : 'border-gray-300' }}">
            <option value="">選択してください</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('exercise_category_id', $exercise?->exercise_category_id) === (string) $category->id)>
                    {{ $category->name }}（{{ $category->repsLabel() }}・{{ $category->rirLabel() }}・休憩{{ $category->restLabel() }}）
                </option>
            @endforeach
        </select>
        @error('exercise_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    @if ($exercise)
        <label class="mb-4 flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_hidden" value="1" @checked(old('is_hidden', $exercise->is_hidden))>
            非表示にする（種目選択に出なくなります。過去の記録・グラフには残ります）
        </label>
    @endif

    <x-form.button>{{ $submit }}</x-form.button>
</form>
