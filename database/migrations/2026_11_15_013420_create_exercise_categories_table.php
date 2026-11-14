<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->unsignedTinyInteger('rep_min');
            $table->unsignedTinyInteger('rep_max');
            $table->unsignedTinyInteger('rir_min');
            $table->unsignedTinyInteger('rir_max');
            $table->unsignedSmallInteger('rest_seconds');
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_categories');
    }
};
