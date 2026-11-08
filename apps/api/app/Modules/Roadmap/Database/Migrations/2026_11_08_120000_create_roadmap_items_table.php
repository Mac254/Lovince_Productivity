<?php

use App\Support\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', Contract::ROADMAP_TITLE_MAX);
            $table->text('details')->nullable();
            $table->string('area', Contract::ROADMAP_AREA_MAX)->nullable();
            $table->string('stage', 16)->default('next');
            $table->unsignedInteger('position')->default(0);
            $table->date('shipped_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stage', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_items');
    }
};
