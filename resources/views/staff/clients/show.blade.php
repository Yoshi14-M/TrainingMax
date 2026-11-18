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

    {{-- 指導メモ（スタッフ専用。利用者本人には見えない。編集・削除は同じジムのスタッフなら誰でもできる） --}}
    <section id="notes" class="mb-6 scroll-mt-32">
        <h2 class="mb-1 text-base font-semibold">指導メモ</h2>
        <p class="mb-3 text-xs text-gray-600">スタッフだけが見られます。利用者本人には表示されません。</p>

        @foreach ($categories as $category)
            @php($group = $notes->get($category->value, collect()))
            @continue($group->isEmpty())
            <h3 class="mb-1 mt-3 text-sm font-medium text-gray-700">{{ $category->label() }}</h3>
            @foreach ($group as $note)
                <div id="note-{{ $note->id }}" class="mb-2 scroll-mt-32 rounded-xl bg-white p-4 shadow">
                    @if ($editingNoteId === $note->id)
                        <form method="POST" action="{{ route('staff.clients.notes.update', [$client, $note]) }}">
                            @csrf
                            @method('PUT')
                            <select name="category" aria-label="分類" class="mb-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                                @foreach ($categories as $option)
                                    <option value="{{ $option->value }}" @selected(old('category', $note->category->value) === $option->value)>{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mb-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            <textarea name="body" rows="4" maxlength="1000" required aria-label="本文" @class(['block w-full rounded-md border px-3 py-2 text-base', $errors->has('body') ? 'border-red-500' : 'border-gray-300'])>{{ old('body', $note->body) }}</textarea>
                            @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            <div class="mt-2 flex justify-end gap-2">
                                <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('staff.clients.show', $client) }}#note-{{ $note->id }}">キャンセル</a>
                                <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">更新</button>
                            </div>
                        </form>
                    @else
                        <p class="whitespace-pre-wrap text-sm">{{ $note->body }}</p>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs text-gray-500">入力：{{ $note->creator->name }}{{ $note->creator->isSuspended() ? '（停止中）' : '' }}／更新：{{ $note->updater->name }}{{ $note->updater->isSuspended() ? '（停止中）' : '' }}・{{ $note->updated_at->isoFormat('YYYY/MM/DD HH:mm') }}</span>
                            <div class="flex items-center gap-2">
                                <a class="rounded-md border px-3 py-1 text-sm text-blue-700" href="{{ route('staff.clients.show', $client) }}?edit-note={{ $note->id }}#note-{{ $note->id }}">編集</a>
                                <x-confirm-modal :action="route('staff.clients.notes.destroy', [$client, $note])" message="この指導メモを削除します。よろしいですか？" button="削除" />
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endforeach

        @if ($notes->isEmpty())
            <p class="mb-3 text-sm text-gray-600">指導メモはまだありません。</p>
        @endif

        <form method="POST" action="{{ route('staff.clients.notes.store', $client) }}" class="mt-3 rounded-xl bg-white p-4 shadow">
            @csrf
            <label for="note-category" class="mb-1 block text-sm font-medium">分類</label>
            <select id="note-category" name="category" class="mb-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-base">
                @foreach ($categories as $option)
                    <option value="{{ $option->value }}" @selected(! $editingNoteId && old('category') === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
            @if (! $editingNoteId)
                @error('category')<p class="mb-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @endif
            <label for="note-body" class="mb-1 block text-sm font-medium">本文</label>
            <textarea id="note-body" name="body" rows="4" maxlength="1000" required @class(['block w-full rounded-md border px-3 py-2 text-base', $errors->has('body') && ! $editingNoteId ? 'border-red-500' : 'border-gray-300'])>{{ $editingNoteId ? '' : old('body') }}</textarea>
            @if (! $editingNoteId)
                @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @endif
            <div class="mt-2 flex justify-end">
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">メモを追加</button>
            </div>
        </form>
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
