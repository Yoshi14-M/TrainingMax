<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 4, 1)->nullable();
            $table->decimal('body_fat_percent', 3, 1)->nullable();
            $table->decimal('muscle_mass_kg', 4, 1)->nullable();
            $table->decimal('waist_cm', 4, 1)->nullable();
            $table->decimal('chest_cm', 4, 1)->nullable();
            $table->decimal('upper_arm_cm', 4, 1)->nullable();
            $table->decimal('thigh_cm', 4, 1)->nullable();
            $table->decimal('hip_cm', 4, 1)->nullable();
            $table->decimal('calf_cm', 4, 1)->nullable();
            $table->decimal('neck_cm', 4, 1)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // 1日1件
            $table->unique(['user_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_compositions');
    }
};
