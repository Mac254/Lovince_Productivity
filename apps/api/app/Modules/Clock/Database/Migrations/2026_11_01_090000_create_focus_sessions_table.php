<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_note_id')->nullable()->constrained()->nullOnDelete();
            $table->date('started_on');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedSmallInteger('planned_minutes');
            $table->unsignedInteger('focused_seconds');
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_sessions');
    }
};
