{{-- トレーニング／体組成の切り替えタブ --}}
<div class="mb-4 flex gap-2 text-sm">
    <a href="{{ route('records.graphs.training', $client) }}"
       @class(['rounded-md border px-4 py-2', 'border-blue-600 bg-blue-50 font-semibold text-blue-800' => $active === 'training'])>トレーニング</a>
    <a href="{{ route('records.graphs.body', $client) }}"
       @class(['rounded-md border px-4 py-2', 'border-blue-600 bg-blue-50 font-semibold text-blue-800' => $active === 'body'])>体組成</a>
</div>
