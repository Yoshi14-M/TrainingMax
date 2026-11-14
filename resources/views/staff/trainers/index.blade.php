<x-layouts.app title="トレーナー管理">
    <section class="mb-8 rounded-xl bg-white p-6 shadow">
        <h2 class="mb-3 font-semibold">トレーナーを追加</h2>
        <p class="mb-4 text-sm">名前とメールアドレスを入力すると、パスワード設定メールを送信します。</p>

        <form method="POST" action="{{ route('staff.trainers.store') }}">
            @csrf
            <x-form.input name="name" label="名前" autocomplete="off" maxlength="50" />
            <x-form.input name="email" label="メールアドレス" type="email" autocomplete="off" maxlength="255" />

            <x-form.button>追加してメールを送信</x-form.button>
        </form>
    </section>

    <section>
        <h2 class="mb-3 font-semibold">トレーナー一覧</h2>

        @forelse ($trainers as $trainer)
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 shadow">
                <div>
                    <p class="font-medium">{{ $trainer->name }}</p>
                    <p class="text-sm text-gray-600">{{ $trainer->email }}</p>
                    <p class="mt-1 text-xs">
                        @if ($trainer->isSuspended())
                            <span class="rounded bg-red-100 px-2 py-0.5 text-red-800">停止中</span>
                        @elseif ($trainer->password === null)
                            <span class="rounded bg-yellow-100 px-2 py-0.5 text-yellow-800">パスワード未設定</span>
                        @else
                            <span class="rounded bg-green-100 px-2 py-0.5 text-green-800">有効</span>
                        @endif
                    </p>
                </div>

                @if ($trainer->isSuspended())
                    <form method="POST" action="{{ route('staff.trainers.suspension.destroy', $trainer) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border px-3 py-2 text-sm">停止を解除</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('staff.trainers.suspension.store', $trainer) }}"
                          onsubmit="return confirm('{{ $trainer->name }}さんを停止します。ログイン中の場合は次の操作でログアウトされます。よろしいですか？')">
                        @csrf
                        <button type="submit" class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700">停止する</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-600">トレーナーはまだいません。</p>
        @endforelse
    </section>
</x-layouts.app>
