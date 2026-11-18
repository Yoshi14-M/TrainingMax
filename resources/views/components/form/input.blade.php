@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'required' => true, 'bag' => 'default', 'id' => null])

<div class="mb-4">
    <label for="{{ $id ?? $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input
        id="{{ $id ?? $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @required($required)
        {{ $attributes->class(['block w-full rounded-md border px-3 py-2 text-base', $errors->getBag($bag)->has($name) ? 'border-red-500' : 'border-gray-300']) }}
    >
    @if ($errors->getBag($bag)->has($name))
        <p class="mt-1 text-sm text-red-600">{{ $errors->getBag($bag)->first($name) }}</p>
    @endif
</div>
