<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $statusMap = [
            'todo' => TaskStatus::Todo->value,
            'doing' => TaskStatus::Doing->value,
            'done' => TaskStatus::Done->value,
        ];

        foreach ($statusMap as $old => $new) {
            DB::table('tasks')->where('status', $old)->update(['status' => $new]);
        }

        $priorityMap = [
            'low' => TaskPriority::Low->value,
            'medium' => TaskPriority::Medium->value,
            'high' => TaskPriority::High->value,
        ];

        foreach ($priorityMap as $old => $new) {
            DB::table('tasks')->where('priority', $old)->update(['priority' => $new]);
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->default(TaskStatus::Todo->value)->change();
            $table->string('priority')->default(TaskPriority::Low->value)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing row values are intentionally not reverted because we cannot distinguish
        // rows that were originally lowercase from rows already created with Title Case values.
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->default('todo')->change();
            $table->string('priority')->default('low')->change();
        });
    }
};
