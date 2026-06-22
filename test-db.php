<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$creds = [
    ['postgres', 'root'],
    ['postgres', 'postgres'],
    ['postgres', ''],
    ['root', 'root'],
    ['root', 'postgres'],
    ['root', '']
];

foreach ($creds as $cred) {
    config([
        'database.connections.pgsql.username' => $cred[0],
        'database.connections.pgsql.password' => $cred[1],
        'database.connections.pgsql.database' => 'postgres' // connect to default db first to avoid missing db error
    ]);
    try {
        DB::purge('pgsql');
        DB::connection('pgsql')->getPdo();
        echo "SUCCESS: " . $cred[0] . ":" . $cred[1] . "\n";
        
        // now create tam_api if it doesn't exist
        $exists = DB::connection('pgsql')->select("SELECT datname FROM pg_catalog.pg_database WHERE datname = 'tam_api'");
        if (empty($exists)) {
            DB::connection('pgsql')->statement('CREATE DATABASE tam_api');
            echo "Created tam_api database!\n";
        } else {
            echo "tam_api database already exists.\n";
        }
        exit;
    } catch (\Exception $e) {
        echo "FAILED: " . $cred[0] . ":" . $cred[1] . " -> " . $e->getMessage() . "\n";
    }
}
