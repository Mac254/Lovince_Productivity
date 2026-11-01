<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('estimate_minutes')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('estimate_minutes'));
    }
};
