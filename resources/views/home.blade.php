{{-- 暫定のトップ画面。ロール別のホームは後続のイシューで実装する --}}
<x-layouts.app title="ホーム">
    <p class="text-sm">{{ auth()->user()->name }}さん、ログインしました。</p>
</x-layouts.app>
