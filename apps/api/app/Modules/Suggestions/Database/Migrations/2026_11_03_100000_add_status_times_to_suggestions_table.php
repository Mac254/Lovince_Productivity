<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
        });

        DB::table('suggestions')->where('status', 'accepted')->update(['accepted_at' => DB::raw('updated_at')]);
        DB::table('suggestions')->where('status', 'done')->update(['done_at' => DB::raw('updated_at')]);
        DB::table('suggestions')->where('status', 'dismissed')->update(['dismissed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'done_at', 'dismissed_at']);
        });
    }
};
