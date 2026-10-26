<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('tone', 20)->default('green')->after('timeframe');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_highlighted')->default(false)->after('category');
            $table->index(['user_id', 'is_highlighted']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_highlighted']);
            $table->dropColumn('is_highlighted');
        });
        Schema::table('goals', fn (Blueprint $table) => $table->dropColumn('tone'));
    }
};
