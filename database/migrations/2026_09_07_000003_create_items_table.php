<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->enum('type', ['note', 'subproject'])->default('note');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['board_column_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
