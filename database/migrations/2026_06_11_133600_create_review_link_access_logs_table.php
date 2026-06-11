<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_link_access_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_link_id')->nullable()->constrained('review_links')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('action');
            $table->string('result');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['review_link_id', 'action']);
            $table->index(['project_id', 'document_id']);
            $table->index(['action', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_link_access_logs');
    }
};
