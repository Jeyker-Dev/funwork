<?php

use App\Enums\RepositoryConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('repository');
            $table->string('branch');
            $table->string('docs_path')->default('docs');
            $table->text('token');
            $table->text('webhook_secret');
            $table->string('last_synced_sha')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('status')->default(RepositoryConnectionStatus::Active->value);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_connections');
    }
};
