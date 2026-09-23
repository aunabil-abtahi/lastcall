<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/database.php';

try {
    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "<h1>Connected to Database: " . htmlspecialchars($db) . "</h1>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>Tables in database:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
