<?php
require_once __DIR__ . '/config/database.php';

try {
    $hash = password_hash('123admin', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE role = 'admin'");
    $stmt->execute([$hash]);
    echo "Admin password updated successfully!\n";
} catch (Exception $e) {
    echo "Error updating password: " . $e->getMessage() . "\n";
}
