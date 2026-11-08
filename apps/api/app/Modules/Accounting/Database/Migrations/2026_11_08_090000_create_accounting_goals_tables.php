<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('name', 80);
            $table->string('kind', 20);
            $table->unsignedBigInteger('target_amount');
            $table->char('currency', 3);
            $table->date('target_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('accounting_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('accounting_goals')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->date('date');
            $table->string('note', 160)->nullable();
            $table->timestamps();

            $table->index(['goal_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_goal_contributions');
        Schema::dropIfExists('accounting_goals');
    }
};
