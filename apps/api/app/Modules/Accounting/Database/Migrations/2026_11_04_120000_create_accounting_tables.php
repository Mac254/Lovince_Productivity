<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->char('currency', 3);
            $table->bigInteger('opening_balance')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('accounting_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('name', 60);
            $table->boolean('is_default')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'name']);
        });

        Schema::create('accounting_transactions', function (Blueprint $table) {
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
            $table->date('date');
            $table->string('description', 160);
            $table->text('notes')->nullable();
            $table->string('reference', 80)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('accounting_transactions')->cascadeOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('accounting_transactions')->cascadeOnDelete();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->char('currency', 3);

            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['kind', 'journal_entry_id']);
        });

        Schema::create('accounting_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_logs');
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_transactions');
        Schema::dropIfExists('accounting_categories');
        Schema::dropIfExists('accounting_accounts');
    }
};
