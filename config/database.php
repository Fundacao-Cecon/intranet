<?php

$postgres = [
    'driver' => 'pgsql', 'host' => env('DB_HOST', 'db'), 'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'intranet_dev'), 'username' => env('DB_USERNAME', 'intranet_app'),
    'password' => env('DB_PASSWORD', ''), 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true,
    'search_path' => 'public', 'sslmode' => env('DB_SSLMODE', 'prefer'),
];

return [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => $postgres,
        'pgsql_migrate' => array_replace($postgres, [
            'username' => env('DB_MIGRATION_USERNAME', 'intranet_migrator'),
            'password' => env('DB_MIGRATION_PASSWORD', ''),
        ]),
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
