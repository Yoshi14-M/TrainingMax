<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->foreignId('gym_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('body_part_id')->constrained()->restrictOnDelete();
            $table->foreignId('exercise_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->decimal('weight_step', 4, 2)->default(2.5);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['scope', 'gym_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
