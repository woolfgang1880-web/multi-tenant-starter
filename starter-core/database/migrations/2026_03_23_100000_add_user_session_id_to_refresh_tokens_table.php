<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->foreignId('user_session_id')->nullable()->after('user_id')->constrained('user_sessions')->nullOnDelete();
            $table->index('user_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_session_id']);
            $table->dropColumn('user_session_id');
        });
    }
};
