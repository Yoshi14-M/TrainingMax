<x-layouts.app title="利用者アカウント発行">
    <p class="mb-4 text-sm">ジム所属の利用者としてアカウントを作成し、パスワード設定メールを送信します。メールのリンクの有効期限は72時間です。</p>

    <form method="POST" action="{{ route('staff.clients.store') }}" class="rounded-xl bg-white p-6 shadow">
        @csrf
        <x-form.input name="name" label="名前" autocomplete="off" maxlength="50" />
        <x-form.input name="email" label="メールアドレス" type="email" autocomplete="off" maxlength="255" />

        <x-form.button>アカウントを発行してメールを送信</x-form.button>
    </form>
</x-layouts.app>
