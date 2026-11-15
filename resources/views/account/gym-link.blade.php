<x-layouts.app title="ジム連携">
    @if ($gym)
        <section class="rounded-xl bg-white p-6 shadow">
            <p class="mb-1 text-sm text-gray-600">所属しているジム</p>
            <p class="mb-4 text-lg font-semibold">{{ $gym->name }}</p>

            <form method="POST" action="{{ route('gym-link.destroy') }}"
                  onsubmit="return confirm('ジムとの紐付けを解除します。\n・記録は利用者に残り、個人利用者として使い続けられます。\n・ジムのトレーナーは記録を閲覧できなくなります。\nよろしいですか？')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700">ジムとの紐付けを解除</button>
            </form>
        </section>
    @else
        <section class="rounded-xl bg-white p-6 shadow">
            <p class="mb-4 text-sm">ジムから受け取った招待コードを入力してください。</p>

            <form method="POST" action="{{ route('gym-link.confirm') }}">
                @csrf
                <x-form.input name="code" label="招待コード" autocomplete="off" maxlength="8" class="uppercase tracking-widest" />
                <x-form.button>確認</x-form.button>
            </form>
        </section>
    @endif
</x-layouts.app>
