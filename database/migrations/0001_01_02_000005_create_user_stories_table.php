<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('epic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('new'); // Enum: new, in_progress, done
            $table->decimal('total_points', 8, 2)->default(0);
            $table->json('custom_fields')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('sprint_id');
            $table->index('epic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stories');
    }
};
