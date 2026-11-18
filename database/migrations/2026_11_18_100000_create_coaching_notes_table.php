<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_notes', function (Blueprint $table) {
            $table->id();
            // 指導メモはジムのもの。紐付け解除後もジム側に残る
            $table->foreignId('gym_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->restrictOnDelete();
            $table->string('category', 20);
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['gym_id', 'client_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_notes');
    }
};
