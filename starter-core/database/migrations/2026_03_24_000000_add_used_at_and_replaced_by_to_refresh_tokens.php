<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable()->after('revoked_at');
            $table->foreignId('replaced_by_token_id')->nullable()->after('used_at')
                ->constrained('refresh_tokens')->nullOnDelete();
            $table->index('used_at');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['replaced_by_token_id']);
            $table->dropColumn(['used_at', 'replaced_by_token_id']);
        });
    }
};
