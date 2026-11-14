<x-layouts.guest title="パスワードの設定">
    <p class="mb-4 text-sm">アカウントのパスワードを設定してください。設定が終わるとログインできます。</p>

    <form method="POST" action="{{ route('initial-password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="メールアドレス" type="email" :value="$email" autocomplete="username" />
        <x-form.input name="password" label="パスワード（8文字以上）" type="password" autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="パスワード（確認）" type="password" autocomplete="new-password" />

        <x-form.button>パスワードを設定</x-form.button>
    </form>
</x-layouts.guest>
