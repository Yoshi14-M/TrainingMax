<nav class="border-b bg-white" x-data="{ open: false }" aria-label="メインメニュー">
    <div class="mx-auto flex max-w-3xl items-stretch text-sm">
        <a href="{{ route('home') }}" class="flex-1 py-3 text-center">ホーム</a>
        @foreach ($items as $item)
            <a href="{{ $item['href'] }}" @class(['flex-1 py-3 text-center', 'border-b-2 border-blue-700 font-semibold text-blue-700' => $item['active']])>{{ $item['label'] }}</a>
        @endforeach
        <button type="button" class="flex-1 py-3 text-center" @click="open = !open" :aria-expanded="open">メニュー</button>
    </div>

    <div x-show="open" x-cloak @click.outside="open = false" class="border-t bg-gray-50 px-4 py-3">
        <ul class="mx-auto max-w-3xl space-y-2 text-sm">
            @foreach ($menu as $entry)
                <li><a class="block py-1 text-blue-700 underline" href="{{ $entry['href'] }}">{{ $entry['label'] }}</a></li>
            @endforeach
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="py-1 text-blue-700 underline">ログアウト</button>
                </form>
            </li>
        </ul>
    </div>
</nav>
