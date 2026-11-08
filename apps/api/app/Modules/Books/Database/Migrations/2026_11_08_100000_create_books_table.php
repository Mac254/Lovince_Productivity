<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('author', 120)->nullable();
            $table->string('status', 20)->default('to_find');
            $table->unsignedSmallInteger('chapters_total')->nullable();
            $table->unsignedSmallInteger('chapters_read')->default(0);
            $table->date('started_on')->nullable();
            $table->date('finished_on')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'finished_on']);
        });

        Schema::create('book_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('read_on');
            $table->unsignedSmallInteger('chapters')->default(0);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_on']);
            $table->index(['book_id', 'read_on']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_id');
        });

        Schema::dropIfExists('book_readings');
        Schema::dropIfExists('books');
    }
};
