<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('folder_type');
            $table->string('drive_folder_id');
            $table->string('name');
            $table->string('path')->nullable();
            $table->string('web_view_link')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'folder_type']);
            $table->index(['project_id', 'folder_type']);
            $table->unique(['user_id', 'project_id', 'folder_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folders');
    }
};
