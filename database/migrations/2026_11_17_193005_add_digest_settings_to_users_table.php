<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_enabled')->default(true)->after('timezone');
            $table->unsignedTinyInteger('digest_hour')->default(8)->after('digest_enabled');
            $table->timestamp('last_digest_sent_at')->nullable()->after('digest_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_enabled', 'digest_hour', 'last_digest_sent_at']);
        });
    }
};
