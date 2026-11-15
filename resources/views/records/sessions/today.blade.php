<x-layouts.app title="今日のトレーニング">
    <section class="rounded-xl bg-white p-6 shadow">
        <p class="mb-1 text-sm text-gray-600">{{ now()->isoFormat('YYYY/MM/DD（ddd）') }}</p>
        <p class="mb-4 text-sm">今日のセッションはまだありません。</p>

        <form method="POST" action="{{ route('records.sessions.store', $client) }}">
            @csrf
            <x-form.button>今日のトレーニングを始める</x-form.button>
        </form>
    </section>
</x-layouts.app>
