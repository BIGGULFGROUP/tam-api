<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=tam_api;charset=utf8mb4', 'root', '');
    $res = $pdo->query('SHOW CREATE TABLE personal_access_tokens');
    $row = $res->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'];
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage();
}
