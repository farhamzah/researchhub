<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_validators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('institution')->nullable();
            $table->string('position')->nullable();
            $table->json('expertise_areas')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_global')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'is_active']);
            $table->index(['is_global', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_validators');
    }
};
