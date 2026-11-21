<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // One running page of personal notes, read and edited from the floating buttons.
            $table->text('notes')->nullable()->after('timezone');
            $table->timestamp('notes_updated_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notes', 'notes_updated_at']));
    }
};
