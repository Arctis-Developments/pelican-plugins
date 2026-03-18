<?php

return [
    'enforce_server_creation' => true,

    'defaults' => [
        'max_servers' => null,
        'max_cpu' => null,
        'max_memory' => null,
        'max_disk' => null,
        'max_databases' => null,
        'max_backups' => null,
        'max_allocations' => null,
    ],

    'provisioning' => [
        'default_io' => 1000,
        'default_swap' => 0,
        'default_database_limit' => 0,
        'default_allocation_limit' => 0,
        'default_backup_limit' => 0,
        'default_oom_killer' => false,
        'default_start_on_completion' => false,
        'default_skip_scripts' => false,
    ],
];
