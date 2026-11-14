<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ログインセッション（Laravel 標準の sessions テーブル）。停止時にユーザーの行を削除するために使う。
 */
class UserSession extends Model
{
    protected $table = 'sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;
}
