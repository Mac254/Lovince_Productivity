<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A roadmap item is a phase of a plan: it can follow a goal over a stretch of dates, and "shipped" becomes "done".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->renameColumn('shipped_on', 'done_on');
        });
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->date('starts_on')->nullable()->after('stage');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
        DB::table('roadmap_items')->where('stage', 'shipped')->update(['stage' => 'done']);
    }

    public function down(): void
    {
        DB::table('roadmap_items')->where('stage', 'done')->update(['stage' => 'shipped']);
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goal_id');
            $table->dropColumn(['starts_on', 'ends_on']);
        });
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->renameColumn('done_on', 'shipped_on');
        });
    }
};
