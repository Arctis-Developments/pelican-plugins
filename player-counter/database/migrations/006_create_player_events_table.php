<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('player_key');
            $table->string('player_name');
            $table->string('player_source_id')->nullable();
            $table->string('event_type', 32);
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('fingerprint', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['server_id', 'fingerprint']);
            $table->index(['server_id', 'player_key', 'occurred_at']);
            $table->index(['server_id', 'event_type', 'occurred_at']);
            $table->index(['server_id', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_events');
    }
};
