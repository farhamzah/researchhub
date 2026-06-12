<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('url');
            $table->text('description')->nullable();
            $table->string('category')->default('other');
            $table->text('thumbnail_url')->nullable();
            $table->text('favicon_url')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['research_project_id', 'category']);
            $table->index(['created_by', 'is_pinned']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_links');
    }
};
