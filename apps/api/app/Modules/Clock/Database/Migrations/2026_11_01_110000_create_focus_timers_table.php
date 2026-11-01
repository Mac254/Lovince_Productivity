<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('state');
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();
        });

        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->unique(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('focus_sessions', fn (Blueprint $table) => $table->dropUnique(['user_id', 'started_at']));
        Schema::dropIfExists('focus_timers');
    }
};
