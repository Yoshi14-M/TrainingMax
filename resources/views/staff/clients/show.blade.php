<x-layouts.app :title="$client->name">
    @php
        $row = 'flex flex-wrap justify-between gap-2 border-b py-2 text-sm last:border-b-0';
    @endphp

    <section class="mb-6 rounded-xl bg-white p-4 shadow">
        <dl>
            <div class="{{ $row }}"><dt class="text-gray-600">名前</dt><dd>{{ $client->name }}</dd></div>
            <div class="{{ $row }}"><dt class="text-gray-600">メールアドレス</dt><dd>{{ $client->email }}</dd></div>
            <div class="{{ $row }}"><dt class="text-gray-600">身長</dt><dd>{{ $client->height_cm !== null ? \App\Support\Format::oneDecimal($client->height_cm).'cm' : '未登録' }}</dd></div>
            <div class="{{ $row }}">
                <dt class="text-gray-600">生年月日</dt>
                <dd>{{ $client->birth_date ? $client->birth_date->isoFormat('YYYY/MM/DD').'（'.$client->birth_date->age.'歳）' : '未登録' }}</dd>
            </div>
            <div class="{{ $row }}"><dt class="text-gray-600">性別</dt><dd>{{ $client->gender?->label() ?? '未登録' }}</dd></div>
            <div class="{{ $row }}">
                <dt class="text-gray-600">最新の体重</dt>
                <dd>{{ $weight ? \App\Support\Format::weight($weight->weight_kg).'（'.$weight->measured_on->isoFormat('YYYY/MM/DD').'）' : '記録なし' }}</dd>
            </div>
            <div class="{{ $row }}">
                <dt class="text-gray-600">最新の体脂肪率</dt>
                <dd>{{ $body_fat ? \App\Support\Format::oneDecimal($body_fat->body_fat_percent).'%（'.$body_fat->measured_on->isoFormat('YYYY/MM/DD').'）' : '記録なし' }}</dd>
            </div>
            <div class="{{ $row }}">
                <dt class="text-gray-600">最終トレーニング日</dt>
                <dd>{{ \App\Support\Format::lastTrained($last_trained_on?->toDateString()) }}</dd>
            </div>
            @if ($client->password === null)
                <div class="{{ $row }}"><dt class="text-gray-600">状態</dt><dd><span class="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">パスワード未設定</span></dd></div>
            @endif
        </dl>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('records.home', $client) }}" class="rounded-md bg-gray-900 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-700">この利用者の記録を開く</a>

        {{-- パスワード未設定の間だけ、設定メールを再送できる（同じ利用者へは1分に1回まで） --}}
        @can('sendInitialPasswordMail', $client)
            <form method="POST" action="{{ route('staff.clients.initial-password-mail', $client) }}">
                @csrf
                <button type="submit" class="rounded-md border px-4 py-3 text-sm">パスワード設定メールを再送</button>
            </form>
        @endcan

        @can('unlink', $client)
            <x-confirm-modal :action="route('staff.clients.membership.destroy', $client)"
                             :message="'「'.$client->name.'」さんのジムとの紐付けを解除します。'.PHP_EOL.'・記録は利用者に残り、個人利用者として使い続けられます。'.PHP_EOL.'・ジムのトレーナーは記録を閲覧できなくなります。'"
                             button="紐付けを解除" confirm="解除する" class="ml-auto" />
        @endcan
    </div>

    <p class="mt-6 text-center text-sm">
        <a class="text-blue-700 underline" href="{{ route('staff.clients.index') }}">利用者一覧へ戻る</a>
    </p>
</x-layouts.app>
