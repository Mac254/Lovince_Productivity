<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('accounting_categories')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'currency']);
        });

        Schema::create('accounting_recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounting_accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->string('type', 20);
            $table->string('direction', 3);
            $table->string('status', 12);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('description', 160);
            $table->text('notes')->nullable();
            $table->string('reference', 80)->nullable();
            $table->string('frequency', 10);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_date')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'next_date']);
            $table->index('next_date');
        });

        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')->nullable()->constrained('accounting_recurring_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_transaction_id');
        });
        Schema::dropIfExists('accounting_recurring_transactions');
        Schema::dropIfExists('accounting_budgets');
    }
};
