<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('key')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('category', 20);
            $table->string('status', 20)->default('new');
            $table->json('action')->nullable();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
