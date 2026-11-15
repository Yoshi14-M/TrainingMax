<x-layouts.app :title="$title">
    @if ($canCreate)
        {{-- 登録の直後・登録のエラーのときは開いたままにして、結果（完了メッセージ・エラー）を見られるようにする --}}
        <details class="mb-6" @if (session('keep_form_open') || ($errors->any() && ! $editingId)) open @endif>
            <summary class="cursor-pointer rounded-md border bg-white px-4 py-3 text-sm font-medium">種目を登録する</summary>
            <div class="mt-3">
                <x-exercise.form :action="$storeUrl" :parts="$parts" :categories="$categories" submit="登録" />
            </div>
        </details>
    @endif

    @forelse ($groups as $group)
        <section class="mb-6">
            <h2 class="mb-2 text-base font-semibold">{{ $group['bodyPart']->name }}</h2>

            @foreach ($group['exercises'] as $exercise)
                <div id="exercise-{{ $exercise->id }}" class="mb-2 scroll-mt-32 rounded-xl bg-white p-4 shadow {{ $exercise->is_hidden ? 'opacity-60' : '' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $exercise->name }}</span>
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $exercise->scope->label() }}</span>
                        @if ($exercise->is_hidden)
                            <span class="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">非表示</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-700">
                        {{ $exercise->category->name }}／{{ $exercise->category->repsLabel() }}・{{ $exercise->category->rirLabel() }}・休憩{{ $exercise->category->restLabel() }}
                    </p>
                    <p class="mt-1 text-sm"><x-youtube-link :name="$exercise->name" /></p>

                    @can('update', $exercise)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a class="rounded-md border px-3 py-1.5 text-sm" href="{{ $indexUrl }}?edit={{ $exercise->id }}#exercise-{{ $exercise->id }}">編集</a>
                            <form method="POST" action="{{ $destroyUrl($exercise) }}"
                                  onsubmit="return confirm('「{{ $exercise->name }}」を削除します。記録で使われている場合は、削除せず非表示になります。よろしいですか？')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700">削除</button>
                            </form>
                        </div>

                        @if ($editingId === $exercise->id)
                            <div class="mt-3">
                                <x-exercise.form :action="$updateUrl($exercise)" method="PUT" :exercise="$exercise" :parts="$parts" :categories="$categories" submit="更新" />
                            </div>
                        @endif
                    @endcan
                </div>
            @endforeach
        </section>
    @empty
        <p class="text-sm text-gray-600">表示できる種目はありません。</p>
    @endforelse
</x-layouts.app>
