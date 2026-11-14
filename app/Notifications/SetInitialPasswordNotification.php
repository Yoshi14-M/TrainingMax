<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * スタッフが発行したアカウントの、パスワード設定メール（有効期限72時間）。
 * 本文にパスワードや記録の内容は書かない。
 */
class SetInitialPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('initial-password.show', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $hours = intdiv((int) config('auth.passwords.initial.expire'), 60);

        return (new MailMessage)
            ->subject('【'.config('app.name').'】パスワードの設定')
            ->greeting($notifiable->name.'さん')
            ->line(config('app.name').'のアカウントが作成されました。')
            ->line('下のボタンからパスワードを設定してください。設定が終わるとログインできます。')
            ->action('パスワードを設定する', $url)
            ->line("このリンクの有効期限は{$hours}時間です。期限が切れた場合は、ジムのスタッフに再送を依頼してください。")
            ->line('このメールに心当たりがない場合は、何もする必要はありません。');
    }
}
