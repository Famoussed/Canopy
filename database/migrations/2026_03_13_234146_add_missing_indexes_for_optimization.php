<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_stories', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('order');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('status');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('sprint_scope_changes', function (Blueprint $table) {
            $table->index('sprint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_stories', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['order']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['status']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('sprint_scope_changes', function (Blueprint $table) {
            $table->dropIndex(['sprint_id']);
        });
    }
};
