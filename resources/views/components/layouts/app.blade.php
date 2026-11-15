@props(['title' => null, 'actions' => null, 'timerRestSeconds' => null, 'timerReturnUrl' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="sticky top-0 z-20 bg-white shadow-sm">
        <div class="mx-auto max-w-3xl px-4 pt-3">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">{{ config('app.name') }}</a>
        </div>
        <x-main-nav />
    </header>

    @php($band = request()->route('client'))
    @if ($band instanceof \App\Models\User && ! auth()->user()->is($band))
        {{-- スタッフが利用者の記録を表示している間の帯 --}}
        <div class="border-b bg-yellow-50 text-sm">
            <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-2 px-4 py-2">
                <span>▶ {{ $band->name }}さんの記録を表示中</span>
                <a class="text-blue-700 underline" href="{{ route('home') }}">戻る</a>
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-3xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        @if ($title)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold">{{ $title }}</h1>
                {{-- タイトルの横に置く操作（例：この日の記録を削除） --}}
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        @endif

        {{ $slot }}
    </main>
    {{-- 休憩タイマー：どの画面でも、作動中（または終了後）は画面下部に表示する。記録画面では、種目を選ぶと推奨休憩時間がセットされる --}}
    <x-workout-timer :rest-seconds="$timerRestSeconds" :return-url="$timerReturnUrl" />
</body>
</html>
