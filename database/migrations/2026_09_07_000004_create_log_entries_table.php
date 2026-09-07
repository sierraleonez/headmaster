<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'logged_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
