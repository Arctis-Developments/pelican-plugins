<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('server_split_quotas')) {
            return;
        }

        Schema::table('server_split_quotas', function (Blueprint $table) {
            if (!Schema::hasColumn('server_split_quotas', 'max_databases')) {
                $table->unsignedInteger('max_databases')->nullable()->after('max_disk');
            }

            if (!Schema::hasColumn('server_split_quotas', 'max_backups')) {
                $table->unsignedInteger('max_backups')->nullable()->after('max_databases');
            }

            if (!Schema::hasColumn('server_split_quotas', 'max_allocations')) {
                $table->unsignedInteger('max_allocations')->nullable()->after('max_backups');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('server_split_quotas')) {
            return;
        }

        Schema::table('server_split_quotas', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('server_split_quotas', 'max_databases') ? 'max_databases' : null,
                Schema::hasColumn('server_split_quotas', 'max_backups') ? 'max_backups' : null,
                Schema::hasColumn('server_split_quotas', 'max_allocations') ? 'max_allocations' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
