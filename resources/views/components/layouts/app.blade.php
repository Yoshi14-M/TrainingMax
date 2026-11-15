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
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-2 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">{{ config('app.name') }}</a>

            <nav class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                @can('accessStaffArea', \App\Models\User::class)
                    <a class="text-blue-700 underline" href="{{ route('staff.clients.create') }}">利用者アカウント発行</a>
                @endcan
                @can('create', \App\Models\InvitationCode::class)
                    <a class="text-blue-700 underline" href="{{ route('staff.invitation-codes.index') }}">招待コード</a>
                @endcan
                @if (auth()->user()->isClient())
                    <a class="text-blue-700 underline" href="{{ route('gym-link.show') }}">ジム連携</a>
                @endif
                @can('manageTrainers', \App\Models\User::class)
                    <a class="text-blue-700 underline" href="{{ route('staff.trainers.index') }}">トレーナー管理</a>
                @endcan
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-blue-700 underline">ログアウト</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        @if ($title)
            <h1 class="mb-4 text-xl font-semibold">{{ $title }}</h1>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
