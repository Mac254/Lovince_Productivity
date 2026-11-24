<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('kind', 10)->default('book')->after('goal_id');
            $table->index(['user_id', 'kind']);
        });

        Schema::table('book_readings', function (Blueprint $table) {
            $table->string('episode', 200)->nullable()->after('read_on');
            $table->string('impact', 1000)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('book_readings', function (Blueprint $table) {
            $table->dropColumn(['episode', 'impact']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
