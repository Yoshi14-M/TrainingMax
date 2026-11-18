<x-layouts.app title="この日の記録を作成">
    <section class="rounded-xl bg-white p-6 shadow">
        <p class="mb-1 text-sm text-gray-600">{{ $date->isoFormat('YYYY/MM/DD（ddd）') }}</p>
        <p class="mb-4 text-sm">この日のセッションはまだありません。</p>

        <form method="POST" action="{{ route('records.sessions.store', $client) }}">
            @csrf
            <input type="hidden" name="performed_on" value="{{ $date->toDateString() }}">
            <x-form.button>この日の記録を作成</x-form.button>
        </form>

        <p class="mt-4 text-center text-sm">
            <a class="text-blue-700 underline" href="{{ route('records.home', $client) }}?month={{ $date->format('Y-m') }}">カレンダーへ戻る</a>
        </p>
    </section>
</x-layouts.app>
