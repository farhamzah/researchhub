<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_session_resources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supervision_session_id')->constrained('supervision_sessions')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type');
            $table->uuid('resource_id')->nullable();
            $table->string('title')->nullable();
            $table->text('url')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible_to_supervisor')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supervision_session_id', 'resource_type']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_session_resources');
    }
};
