<button {{ $attributes->merge(['type' => 'submit'])->class('w-full rounded-md bg-gray-900 px-4 py-3 text-base font-semibold text-white hover:bg-gray-700') }}>
    {{ $slot }}
</button>
