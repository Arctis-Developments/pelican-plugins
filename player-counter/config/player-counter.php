<?php

return [
    'use_alias' => env('PLAYER_COUNTER_USE_ALIAS', false),
    'history' => [
        'enabled' => env('PLAYER_COUNTER_HISTORY_ENABLED', true),
        'snapshot_interval_seconds' => env('PLAYER_COUNTER_HISTORY_SNAPSHOT_INTERVAL', 300),
    ],
    'minecraft_java_logs' => [
        'enabled' => env('PLAYER_COUNTER_MINECRAFT_JAVA_LOGS_ENABLED', true),
        'max_lines' => env('PLAYER_COUNTER_MINECRAFT_JAVA_LOG_MAX_LINES', 2500),
        'sync_interval_seconds' => env('PLAYER_COUNTER_MINECRAFT_JAVA_LOG_SYNC_INTERVAL', 60),
    ],
];
