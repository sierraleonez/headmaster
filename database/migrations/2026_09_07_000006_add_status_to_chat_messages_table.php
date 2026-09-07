<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // The assistant's reply is written by a queued job, so a message
            // exists on screen before the model has answered.
            $table->enum('status', ['pending', 'ready', 'failed'])
                ->default('ready')
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
