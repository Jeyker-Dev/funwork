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
        Schema::table('repository_connections', function (Blueprint $table) {
            $table->timestamp('sync_requested_at')->nullable()->after('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repository_connections', function (Blueprint $table) {
            $table->dropColumn('sync_requested_at');
        });
    }
};
