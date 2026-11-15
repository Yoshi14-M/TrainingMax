<x-layouts.app title="ジム連携の確認">
    <section class="rounded-xl bg-white p-6 shadow">
        <p class="mb-1 text-sm text-gray-600">紐付けるジム</p>
        <p class="mb-4 text-lg font-semibold">{{ $gym->name }}</p>

        <ul class="mb-4 list-disc space-y-1 pl-5 text-sm">
            <li>紐付けると、これまでの記録（トレーニング・体組成）もジムのトレーナーに共有されます。</li>
            <li>トレーナーもあなたの記録を入力・編集できるようになります。</li>
        </ul>

        <form method="POST" action="{{ route('gym-link.store') }}">
            @csrf
            <label class="mb-4 flex items-start gap-2 text-sm">
                <input type="checkbox" name="consent" value="1" class="mt-1" @checked(old('consent'))>
                上記を確認しました。
            </label>
            @error('consent')
                <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <x-form.button>紐付ける</x-form.button>
        </form>

        <p class="mt-4 text-center text-sm">
            <a class="text-blue-700 underline" href="{{ route('gym-link.show') }}">戻る</a>
        </p>
    </section>
</x-layouts.app>
