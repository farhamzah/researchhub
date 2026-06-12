<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_review_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supervision_session_id')->constrained('supervision_sessions')->cascadeOnDelete();
            $table->foreignUuid('expert_validator_id')->nullable()->constrained('expert_validators')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_role')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('token_hash')->nullable()->unique();
            $table->timestamp('token_created_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['supervision_session_id', 'status']);
            $table->index(['expert_validator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_review_links');
    }
};
