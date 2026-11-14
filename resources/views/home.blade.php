{{-- 暫定のトップ画面。ロール別のホームは後続のイシューで実装する --}}
<x-layouts.guest title="ホーム">
    <p class="mb-4 text-sm">{{ auth()->user()->name }}さん、ログインしました。</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-form.button>ログアウト</x-form.button>
    </form>
</x-layouts.guest>
