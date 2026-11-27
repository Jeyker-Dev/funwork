<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->string('model')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
