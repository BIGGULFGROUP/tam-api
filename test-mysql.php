<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
    'database.connections.mysql.database' => 'mysql', // test DB
    'database.connections.mysql.port' => '3306'
]);

try {
    DB::purge('mysql');
    DB::connection('mysql')->getPdo();
    echo "SUCCESS: MySQL root with empty password connected!\n";
    
    // Create tam_api db
    DB::connection('mysql')->statement('CREATE DATABASE IF NOT EXISTS tam_api');
    echo "tam_api database is ready.\n";
} catch (\Exception $e) {
    echo "FAILED: MySQL -> " . $e->getMessage() . "\n";
}
