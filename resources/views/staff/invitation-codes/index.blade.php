<x-layouts.app title="招待コード">
    <section class="mb-8 rounded-xl bg-white p-6 shadow">
        <h2 class="mb-3 font-semibold">招待コードを発行</h2>
        <p class="mb-4 text-sm">個人利用者がジムに紐付くためのコードです。1回使うと無効になり、有効期限は発行から{{ config('trainingmax.invitation_code_expire_days') }}日です。</p>

        <form method="POST" action="{{ route('staff.invitation-codes.store') }}">
            @csrf
            <x-form.input name="memo" label="メモ（渡す相手の名前など。任意）" :required="false" autocomplete="off" maxlength="100" />
            <x-form.button>招待コードを発行</x-form.button>
        </form>
    </section>

    <section>
        <h2 class="mb-3 font-semibold">招待コード一覧</h2>

        @forelse ($codes as $code)
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 shadow">
                <div>
                    <p class="font-mono text-lg tracking-widest">{{ $code->code }}</p>
                    @if ($code->memo)
                        <p class="text-sm text-gray-600">{{ $code->memo }}</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-600">
                        発行：{{ $code->issuer->name }}（{{ $code->created_at->isoFormat('YYYY/MM/DD HH:mm') }}）／
                        有効期限：{{ $code->expires_at->isoFormat('YYYY/MM/DD HH:mm') }}
                    </p>
                    <p class="mt-1 text-xs">
                        <span class="rounded bg-gray-100 px-2 py-0.5">{{ $code->status()->label() }}</span>
                        @if ($code->usedBy)
                            使用者：{{ $code->usedBy->name }}
                        @endif
                    </p>
                </div>

                @can('revoke', $code)
                    <form method="POST" action="{{ route('staff.invitation-codes.destroy', $code) }}"
                          onsubmit="return confirm('この招待コードを取り消します。よろしいですか？')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700">取消</button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="text-sm text-gray-600">招待コードはまだありません。</p>
        @endforelse

        {{ $codes->links() }}
    </section>
</x-layouts.app>
