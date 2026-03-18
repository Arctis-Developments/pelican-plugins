<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('server_split_quotas')) {
            return;
        }

        Schema::create('server_split_quotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedInteger('max_servers')->nullable();
            $table->unsignedInteger('max_cpu')->nullable();
            $table->unsignedInteger('max_memory')->nullable();
            $table->unsignedInteger('max_disk')->nullable();
            $table->unsignedInteger('max_databases')->nullable();
            $table->unsignedInteger('max_backups')->nullable();
            $table->unsignedInteger('max_allocations')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_split_quotas');
    }
};
