<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Parent card that owns this sub-project. Null for a user's root board.
            // Deliberately unconstrained: projects -> items -> board_columns -> projects
            // is a cycle, and MySQL does not resolve cyclic cascades. The whole
            // subtree is removed by ProjectTree::deleteProject() instead.
            $table->unsignedBigInteger('parent_item_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('kind', ['board', 'log'])->default('board');
            $table->timestamps();

            $table->index(['user_id', 'parent_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
