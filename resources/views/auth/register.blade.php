<x-layouts.guest title="新規登録">
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <x-form.input name="name" label="名前" autocomplete="name" maxlength="50" />
        <x-form.input name="email" label="メールアドレス" type="email" autocomplete="username" maxlength="255" />
        <x-form.input name="password" label="パスワード（8文字以上）" type="password" autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="パスワード（確認）" type="password" autocomplete="new-password" />

        <x-form.button>登録する</x-form.button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a class="text-blue-700 underline" href="{{ route('login') }}">ログインはこちら</a>
    </p>
</x-layouts.guest>
