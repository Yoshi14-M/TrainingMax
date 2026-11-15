<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 重量の刻みは、種目の属性ではなく記録画面で切り替える（同じ種目でもプレートや器具で変わるため）。
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('weight_step');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->decimal('weight_step', 4, 2)->default(2.5)->after('name');
        });
    }
};
