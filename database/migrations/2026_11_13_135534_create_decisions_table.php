<?php

use App\Enums\DecisionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->string('status')->default(DecisionStatus::Proposed->value);
            $table->text('context');
            $table->text('decision');
            $table->text('consequences');
            $table->date('decided_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('decisions')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
