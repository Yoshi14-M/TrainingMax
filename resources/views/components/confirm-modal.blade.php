@props(['action', 'message', 'button' => '削除', 'confirm' => '削除する'])

{{-- 削除の確認ダイアログ（ブラウザ標準の confirm ではなく画面内のモーダル。中に DELETE のフォームを持つ。機能設計書5.3） --}}
<div x-data="{ open: false }" {{ $attributes }}>
    <button type="button" @click="open = true" class="rounded-md border border-red-300 px-3 py-1 text-sm text-red-700">{{ $button }}</button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
        <div @click.outside="open = false" class="w-full max-w-sm rounded-xl bg-white p-5 shadow-lg">
            <p class="mb-4 text-sm">{{ $message }}</p>

            <form method="POST" action="{{ $action }}" class="flex justify-end gap-2">
                @csrf
                @method('DELETE')
                <button type="button" @click="open = false" class="rounded-md border px-3 py-2 text-sm">キャンセル</button>
                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white">{{ $confirm }}</button>
            </form>
        </div>
    </div>
</div>
