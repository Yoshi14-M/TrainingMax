@props(['key'])

@php
    $entry = trans("glossary.{$key}");
    $known = is_array($entry);
@endphp

{{-- 用語の「？」（機能設計書 F-16）。タップで解説を表示し、外側のタップ・Esc・閉じるボタンで閉じる。
     解説文は lang/ja/glossary.php で管理する。表の中でも切れないよう、解説は画面に固定して表示する。
     <p> の中に置かれることがあるため、<div>・<p> は使わない（<p> の中の <div> はブラウザが <p> を閉じてしまい、動作しなくなる） --}}
@if ($known)
    <span x-data="{ open: false }" @keydown.escape.window="open = false" class="inline-block align-baseline">
        <button type="button" @click.prevent.stop="open = ! open" :aria-expanded="open"
                class="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-400 text-[10px] font-semibold leading-none text-gray-600 hover:bg-gray-100"
                aria-label="{{ $entry['term'] }}の解説">?</button>
        <span x-show="open" x-cloak x-transition.opacity @click.outside="open = false" role="dialog" aria-label="{{ $entry['term'] }}の解説"
             class="block fixed inset-x-4 top-24 z-40 mx-auto max-w-sm rounded-xl border bg-white p-4 text-left text-sm font-normal normal-case text-gray-800 shadow-lg">
            <span class="block font-semibold">{{ $entry['term'] }}</span>
            <span class="mt-1 block">{{ $entry['description'] }}</span>
            <button type="button" @click.prevent.stop="open = false" class="mt-3 rounded-md border px-3 py-1 text-xs">閉じる</button>
        </span>
    </span>
@endif
