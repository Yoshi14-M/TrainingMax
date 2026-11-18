<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_comments', function (Blueprint $table) {
            $table->id();
            // セッションを削除するとコメント（返信を含む）も削除される
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            // 返信の場合は、返信先のコメント（1段階のみ。返信への返信は作らない）。返信先を削除すると返信も削除される
            $table->foreignId('parent_id')->nullable()->constrained('session_comments')->cascadeOnDelete();
            $table->text('body');
            // 書いた人（スタッフ、または利用者本人）
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_comments');
    }
};
