<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Http\Responses\PasswordUpdateResponse;
use App\Http\Responses\ProfileInformationUpdatedResponse;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\PasswordUpdateResponse as PasswordUpdateResponseContract;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse as ProfileInformationUpdatedResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 自動のルート登録は止め、routes/auth.php に自分で登録する（回数制限を付けるため）
        Fortify::ignoreRoutes();

        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(ProfileInformationUpdatedResponseContract::class, ProfileInformationUpdatedResponse::class);
        $this->app->singleton(PasswordUpdateResponseContract::class, PasswordUpdateResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        // メールアドレス（小文字に変換）とパスワードで照合する。パスワード未設定（NULL）は必ず失敗
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()
                ->where('email', mb_strtolower((string) $request->input(Fortify::username())))
                ->first();

            if ($user === null || $user->password === null) {
                return null;
            }

            if (! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            // パスワードが正しい場合のみ、停止中であることを伝える（停止状態を第三者に推測させないため）
            if ($user->isSuspended()) {
                Audit::log('login.suspended', ['user_id' => $user->id, 'ip' => $request->ip()]);

                throw ValidationException::withMessages([Fortify::username() => __('auth.suspended')]);
            }

            return $user;
        });

        // 「ログイン状態を保持する」の有効期間（Laravel の初期値は約400日）
        Auth::guard(config('fortify.guard'))->setRememberDuration(config('trainingmax.remember_duration'));

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(config('trainingmax.rate_limits.login'))->by($throttleKey);
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(config('trainingmax.rate_limits.register'))->by($request->ip());
        });

        // 制限を超えても、成功時と同じメッセージを返す（送信はしない）
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(config('trainingmax.rate_limits.password_reset'))
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => back()->with('status', __('passwords.link_requested')));
        });
    }
}
