<?php
$start = microtime(true);
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=tam_api', 'root', '');
    $pdo->query('SELECT 1');
    echo "Connected in " . round((microtime(true) - $start) * 1000, 2) . "ms\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
