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
        Schema::table('notebook_pages', function (Blueprint $table) {
            $table->string('source_path')->nullable()->after('notebook_id');
            $table->unique(['notebook_id', 'source_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notebook_pages', function (Blueprint $table) {
            $table->dropUnique(['notebook_id', 'source_path']);
            $table->dropColumn('source_path');
        });
    }
};
