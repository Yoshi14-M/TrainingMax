<x-layouts.guest title="ログイン">
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <x-form.input name="email" label="メールアドレス" type="email" autocomplete="username" />
        <x-form.input name="password" label="パスワード" type="password" autocomplete="current-password" />

        <div class="mb-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                ログイン状態を保持する
            </label>
            <p class="mt-1 text-xs text-gray-500">共用端末ではチェックしないでください。</p>
        </div>

        <x-form.button>ログイン</x-form.button>
    </form>

    <div class="mt-4 space-y-1 text-center text-sm">
        <p><a class="text-blue-700 underline" href="{{ route('password.request') }}">パスワードをお忘れの方</a></p>
        <p><a class="text-blue-700 underline" href="{{ route('register') }}">新規登録（個人利用者）</a></p>
    </div>
</x-layouts.guest>
