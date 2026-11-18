<x-layouts.app title="利用者一覧">
    {{-- タイトルの横：利用者アカウント発行・招待コード発行 --}}
    <x-slot:actions>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('staff.clients.create') }}" class="rounded-md border px-3 py-2">利用者アカウント発行</a>
            <a href="{{ route('staff.invitation-codes.index') }}" class="rounded-md border px-3 py-2">招待コード発行</a>
        </div>
    </x-slot:actions>

    {{-- 検索（名前の部分一致）と並び順 --}}
    <form method="GET" action="{{ route('staff.clients.index') }}" class="mb-4 rounded-xl bg-white p-4 shadow"
          x-data>
        <div class="mb-3">
            <label for="q" class="mb-1 block text-sm font-medium">名前で検索</label>
            <input id="q" name="q" type="search" maxlength="50" value="{{ $keyword }}" autocomplete="off"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
        </div>

        <div class="mb-3">
            <label for="sort" class="mb-1 block text-sm font-medium">並び順</label>
            <select id="sort" name="sort" @change="$el.form.submit()" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                <option value="last_trained" @selected($sort === 'last_trained')>最終トレーニング日（新しい順）</option>
                <option value="name" @selected($sort === 'name')>名前順</option>
            </select>
        </div>

        <x-form.button>検索</x-form.button>
    </form>

    @forelse ($clients as $client)
        <a href="{{ route('staff.clients.show', $client) }}" class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-4 shadow">
            <div>
                <p class="font-medium">{{ $client->name }}</p>
                @if ($client->password === null)
                    <span class="mt-1 inline-block rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">パスワード未設定</span>
                @endif
            </div>
            <p class="text-sm text-gray-700">{{ \App\Support\Format::lastTrained($client->last_trained_on) }}</p>
        </a>
    @empty
        <p class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow">
            {{ $keyword !== null ? '該当する利用者はいません。' : '利用者がまだいません。「利用者アカウント発行」から追加できます。' }}
        </p>
    @endforelse

    <div class="mt-4">{{ $clients->links() }}</div>
</x-layouts.app>
