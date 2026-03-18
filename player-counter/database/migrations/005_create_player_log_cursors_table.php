<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_log_cursors', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('path');
            $table->string('file_hash', 64)->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_log_cursors');
    }
};
