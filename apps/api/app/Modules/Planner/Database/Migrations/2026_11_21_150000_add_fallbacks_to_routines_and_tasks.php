<?php

use App\Support\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_items', function (Blueprint $table) {
            $table->string('fallback', Contract::FALLBACK_MAX)->nullable()->after('title');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('fallback', Contract::FALLBACK_MAX)->nullable()->after('cancel_reason');
            $table->boolean('fallback_done')->default(false)->after('fallback');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['fallback', 'fallback_done']));
        Schema::table('schedule_items', fn (Blueprint $table) => $table->dropColumn('fallback'));
    }
};
