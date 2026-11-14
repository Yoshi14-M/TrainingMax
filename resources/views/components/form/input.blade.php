@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'required' => true])

<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @required($required)
        {{ $attributes->class(['block w-full rounded-md border px-3 py-2 text-base', $errors->has($name) ? 'border-red-500' : 'border-gray-300']) }}
    >
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
