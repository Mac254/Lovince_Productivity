<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->foreignId('money_goal_id')->nullable()->after('goal_id')->constrained('accounting_goals')->nullOnDelete();
            $table->unsignedBigInteger('target_amount')->nullable()->after('money_goal_id');
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('money_goal_id');
            $table->dropColumn('target_amount');
        });
    }
};
