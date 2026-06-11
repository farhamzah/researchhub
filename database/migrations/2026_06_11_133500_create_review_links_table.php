<?php

use App\Models\ReviewLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('password_hash')->nullable();
            $table->string('label')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_email')->nullable();
            $table->json('permissions');
            $table->string('status')->default(ReviewLink::STATUS_ACTIVE);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_access_count')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['document_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_links');
    }
};
