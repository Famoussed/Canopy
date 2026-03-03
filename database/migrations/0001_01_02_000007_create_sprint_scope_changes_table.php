<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprint_scope_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sprint_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_story_id')->constrained()->cascadeOnDelete();
            $table->string('change_type'); // Enum: added, removed
            $table->timestamp('changed_at');
            $table->foreignUuid('changed_by')->constrained('users')->cascadeOnDelete();

            $table->index(['sprint_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_scope_changes');
    }
};
