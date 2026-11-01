<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('tone', 20)->default('blue');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_type_id')->nullable()->after('category')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_type_id');
        });

        Schema::dropIfExists('task_types');
    }
};
