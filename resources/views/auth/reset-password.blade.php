<x-layouts.guest title="パスワードの再設定">
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form.input name="email" label="メールアドレス" type="email" :value="$request->email" autocomplete="username" />
        <x-form.input name="password" label="新しいパスワード（8文字以上）" type="password" autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="新しいパスワード（確認）" type="password" autocomplete="new-password" />

        <x-form.button>パスワードを再設定</x-form.button>
    </form>
</x-layouts.guest>
