<x-layouts.guest title="ログイン">
    @if (config('demo.enabled'))
        {{-- デモ環境のみ：ロールを選んでログイン（パスワード不要） --}}
        <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4" aria-labelledby="demo-login-heading">
            <h2 id="demo-login-heading" class="font-semibold">{{ __('demo.login_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-700">{{ __('demo.login_note') }}</p>

            <div class="mt-3 space-y-2">
                @foreach (config('demo.accounts') as $key => $account)
                    <form method="POST" action="{{ route('demo.login', $key) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-md bg-white px-3 py-2 text-left shadow-sm ring-1 ring-blue-200 hover:bg-blue-100">
                            <span class="block text-sm font-semibold text-blue-800">{{ $account['label'] }}としてログイン</span>
                            <span class="block text-xs text-gray-600">{{ $account['description'] }}</span>
                        </button>
                    </form>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-gray-600">{{ __('demo.cold_start') }}</p>
        </section>
    @endif

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

    {{-- デモ環境ではメールが届かないため、メールが必要な画面への案内は出さない --}}
    @unless (config('demo.enabled'))
        <div class="mt-4 space-y-1 text-center text-sm">
            <p><a class="text-blue-700 underline" href="{{ route('password.request') }}">パスワードをお忘れの方</a></p>
            <p><a class="text-blue-700 underline" href="{{ route('register') }}">新規登録（個人利用者）</a></p>
        </div>
    @endunless
</x-layouts.guest>
