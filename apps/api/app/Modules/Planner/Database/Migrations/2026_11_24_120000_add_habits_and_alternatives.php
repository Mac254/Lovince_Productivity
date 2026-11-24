<?php

use App\Support\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_items', function (Blueprint $table) {
            $table->json('alternatives')->nullable()->after('fallback');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('habit', Contract::TIME_BLOCK_TITLE_MAX)->nullable()->after('title');
            $table->index(['user_id', 'habit']);
        });

        // A task a schedule laid out on its day is a habit; its slot was its own title.
        DB::table('tasks')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('schedule_items')
                ->join('schedules', 'schedules.id', '=', 'schedule_items.schedule_id')
                ->whereColumn('schedules.user_id', 'tasks.user_id')
                ->whereColumn('schedule_items.title', 'tasks.title'))
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('time_blocks')
                ->whereColumn('time_blocks.task_id', 'tasks.id'))
            ->update(['habit' => DB::raw('title')]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'habit']);
            $table->dropColumn('habit');
        });
        Schema::table('schedule_items', fn (Blueprint $table) => $table->dropColumn('alternatives'));
    }
};
