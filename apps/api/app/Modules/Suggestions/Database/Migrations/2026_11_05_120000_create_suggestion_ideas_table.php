<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestion_ideas', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category', 20);
            $table->string('title');
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->string('feed', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_ideas');
    }
};
