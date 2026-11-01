<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('due_count')->default(0);
            $table->unsignedInteger('due_completed_count')->default(0);
            $table->unsignedInteger('overdue_count')->default(0);
            $table->unsignedInteger('open_blockers_count')->default(0);
            $table->json('data');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
