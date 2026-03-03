<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_story_id')->constrained()->cascadeOnDelete();
            $table->string('role_name', 50); // UX, Design, Frontend, Backend
            $table->decimal('points', 5, 2);

            $table->unique(['user_story_id', 'role_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_points');
    }
};
