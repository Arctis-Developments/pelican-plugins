<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('player_key');
            $table->string('player_name');
            $table->string('player_source_id')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('left_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'left_at']);
            $table->index(['server_id', 'player_key']);
            $table->index(['server_id', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_sessions');
    }
};
