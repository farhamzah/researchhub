<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_follow_up_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supervision_session_id')->constrained('supervision_sessions')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('normal');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supervision_session_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_follow_up_items');
    }
};
