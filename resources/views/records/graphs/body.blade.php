<x-layouts.app title="グラフ">
    @include('records.graphs._tabs', ['active' => 'body'])

    <form method="GET" action="{{ route('records.graphs.body', $client) }}" class="mb-4 rounded-xl bg-white p-4 shadow"
          x-data="{ period: @js($period->key) }">
        @include('records.graphs._period')

        <x-form.button>表示</x-form.button>
    </form>

    <h2 class="mb-2 text-base font-semibold">体組成</h2>

    @include('records.graphs._chart', ['label' => '体組成'])
</x-layouts.app>
