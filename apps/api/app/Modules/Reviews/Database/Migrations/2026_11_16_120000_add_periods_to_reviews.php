<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Weekly reviews become reviews of a week, a month or a year. Existing rows stay weekly. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_reviews', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'week_start']);
        });
        Schema::rename('weekly_reviews', 'reviews');
        Schema::table('reviews', function (Blueprint $table) {
            $table->renameColumn('week_start', 'starts_on');
        });
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('period', 8)->default('week')->after('user_id');
            $table->unique(['user_id', 'period', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'period', 'starts_on']);
        });
        DB::table('reviews')->where('period', '!=', 'week')->delete();
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('period');
            $table->renameColumn('starts_on', 'week_start');
        });
        Schema::rename('reviews', 'weekly_reviews');
        Schema::table('weekly_reviews', function (Blueprint $table) {
            $table->unique(['user_id', 'week_start']);
        });
    }
};
