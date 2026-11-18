<x-layouts.app title="プロフィール・アカウント設定">
    {{-- プロフィール --}}
    <section class="mb-6 rounded-xl bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">プロフィール</h2>

        <form method="POST" action="{{ route('user-profile-information.update') }}" novalidate>
            @csrf
            @method('PUT')

            <x-form.input name="name" label="名前" :value="$user->name" autocomplete="name" maxlength="50" bag="updateProfileInformation" />
            <x-form.input name="email" label="メールアドレス" type="email" :value="$user->email" autocomplete="username" maxlength="255" bag="updateProfileInformation" />
            <p class="-mt-2 mb-4 text-xs text-gray-600">メールアドレスを変更すると、新しいアドレスへ届く認証メールで再度認証が必要になります。</p>
            <x-form.input name="current_password" id="profile_current_password" label="現在のパスワード（メールアドレスを変更する場合のみ）" type="password" autocomplete="current-password" :required="false" bag="updateProfileInformation" />

            @if ($user->isClient())
                <x-form.input name="height_cm" label="身長（cm）" type="number" :value="$user->height_cm" step="0.1" min="100" max="250" inputmode="decimal" :required="false" bag="updateProfileInformation" />
                <x-form.input name="birth_date" label="生年月日" type="date" :value="$user->birth_date?->format('Y-m-d')" min="1900-01-01" :max="now()->format('Y-m-d')" autocomplete="bday" :required="false" bag="updateProfileInformation" />

                <div class="mb-4">
                    <label for="gender" class="mb-1 block text-sm font-medium">性別</label>
                    <select id="gender" name="gender" @class(['block w-full rounded-md border px-3 py-2 text-base', $errors->updateProfileInformation->has('gender') ? 'border-red-500' : 'border-gray-300'])>
                        <option value="">未選択</option>
                        @foreach (\App\Enums\Gender::cases() as $gender)
                            <option value="{{ $gender->value }}" @selected(old('gender', $user->gender?->value) === $gender->value)>{{ $gender->label() }}</option>
                        @endforeach
                    </select>
                    @if ($errors->updateProfileInformation->has('gender'))
                        <p class="mt-1 text-sm text-red-600">{{ $errors->updateProfileInformation->first('gender') }}</p>
                    @endif
                </div>

                <p class="mb-4 text-sm text-gray-700">
                    体重：
                    @if ($weight)
                        <x-weight :value="$weight->weight_kg" />（{{ $weight->measured_on->format('Y/n/j') }}の記録）
                    @else
                        記録なし
                    @endif
                    <span class="block text-xs text-gray-600">体重は体組成の記録で入力します。</span>
                </p>
            @endif

            <x-form.button>プロフィールを更新する</x-form.button>
        </form>
    </section>

    {{-- パスワード変更 --}}
    <section class="rounded-xl bg-white p-6 shadow">
        <h2 class="mb-1 text-lg font-semibold">パスワードの変更</h2>
        <p class="mb-4 text-xs text-gray-600">変更すると、この端末以外のログインは解除されます。</p>

        <form method="POST" action="{{ route('user-password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <x-form.input name="current_password" id="password_current_password" label="現在のパスワード" type="password" autocomplete="current-password" bag="updatePassword" />
            <x-form.input name="password" label="新しいパスワード（8文字以上）" type="password" autocomplete="new-password" bag="updatePassword" />
            <x-form.input name="password_confirmation" label="新しいパスワード（確認）" type="password" autocomplete="new-password" bag="updatePassword" />

            <x-form.button>パスワードを変更する</x-form.button>
        </form>
    </section>
</x-layouts.app>
