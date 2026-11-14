<x-layouts.guest title="メール認証のお願い">
    <p class="mb-4 text-sm">
        ご登録のメールアドレスに認証メールを送信しました。メール内のリンクを押して認証を完了してください。認証が済むまで、記録の機能は使えません。
    </p>

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <x-form.button>認証メールを再送</x-form.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-sm text-blue-700 underline">ログアウト</button>
    </form>
</x-layouts.guest>
