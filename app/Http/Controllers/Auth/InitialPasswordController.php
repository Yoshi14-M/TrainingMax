<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Account\SetInitialPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitialPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * スタッフが発行したアカウントの、パスワード初回設定（S-05）。
 */
class InitialPasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.initial-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(InitialPasswordRequest $request, SetInitialPassword $action): RedirectResponse
    {
        $status = $action->handle($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __("passwords.{$status}")]);
        }

        return redirect()->route('login')->with('status', __('auth.initial_password_set'));
    }
}
