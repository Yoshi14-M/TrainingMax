<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * デモ環境で、ロールを選ぶだけでデモ用アカウントにログインする（DEMO_MODE=true のときだけ）。
 * デモ環境でなければルートが存在しないのと同じ 404 にする。
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request, string $account): RedirectResponse
    {
        abort_unless(Demo::enabled(), 404);

        $email = config("demo.accounts.{$account}.email");
        abort_if($email === null, 404);

        $user = User::query()->where('email', mb_strtolower($email))->first();

        if ($user === null || $user->isSuspended()) {
            return redirect()->route('login')->with('error', __('demo.account_unavailable'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        Audit::log('demo.login', ['user_id' => $user->id]);

        return redirect()->route('home');
    }
}
