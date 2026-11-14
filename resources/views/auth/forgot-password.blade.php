<x-layouts.guest title="パスワードリセット">
    <p class="mb-4 text-sm">登録したメールアドレスを入力してください。再設定用のメールをお送りします。</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-form.input name="email" label="メールアドレス" type="email" autocomplete="username" />
        <x-form.button>再設定メールを送信</x-form.button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a class="text-blue-700 underline" href="{{ route('login') }}">ログインへ戻る</a>
    </p>
</x-layouts.guest>
