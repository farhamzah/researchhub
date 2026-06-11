<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('research_type')->nullable();
            $table->string('institution')->nullable();
            $table->string('status')->default('draft');
            $table->date('started_at')->nullable();
            $table->date('target_finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_id', 'slug']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_projects');
    }
};
