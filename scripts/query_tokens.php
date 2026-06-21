<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=tam_api;charset=utf8mb4', 'root', '');
    $stmt = $pdo->prepare("SELECT id, tokenable_type, tokenable_id, name, token, created_at, last_used_at FROM personal_access_tokens WHERE tokenable_type = :t ORDER BY id DESC LIMIT 10");
    $stmt->execute([':t' => 'App\\Models\\AdminProfile']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage();
}
