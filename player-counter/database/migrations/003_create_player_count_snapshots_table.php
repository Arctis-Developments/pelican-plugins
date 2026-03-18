<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_count_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('hostname')->nullable();
            $table->string('map')->nullable();
            $table->unsignedInteger('current_players')->default(0);
            $table->unsignedInteger('max_players')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['server_id', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_count_snapshots');
    }
};
