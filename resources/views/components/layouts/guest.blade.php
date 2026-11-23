@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    @if (config('demo.enabled'))
        <div class="bg-blue-700 px-4 py-1.5 text-center text-xs text-white" role="note">{{ __('demo.banner') }}</div>
    @endif
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-8">
        <p class="mb-6 text-center text-2xl font-bold tracking-tight">{{ config('app.name') }}</p>

        <div class="rounded-xl bg-white p-6 shadow">
            @if ($title)
                <h1 class="mb-4 text-lg font-semibold">{{ $title }}</h1>
            @endif

            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
            @endif

            {{ $slot }}
        </div>
    </main>
</body>
</html>
