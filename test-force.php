<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/database.php';

echo "<pre>";
echo "DB Name from config: $dbname\n";

try {
    $res = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "Current DB from MySQL: $res\n";
} catch (Exception $e) { echo "DB err: " . $e->getMessage() . "\n"; }

try {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n";
} catch (Exception $e) { echo "Tables err: " . $e->getMessage() . "\n"; }

try {
    $pdo->query("DROP TABLE IF EXISTS locations");
    echo "Dropped locations successfully.\n";
} catch (Exception $e) { echo "Drop locations err: " . $e->getMessage() . "\n"; }

try {
    $pdo->query("CREATE TABLE locations (id INT) ENGINE=InnoDB");
    echo "Created locations successfully.\n";
} catch (Exception $e) { echo "Create locations err: " . $e->getMessage() . "\n"; }

try {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables after create: " . implode(", ", $tables) . "\n";
} catch (Exception $e) { echo "Tables err2: " . $e->getMessage() . "\n"; }

echo "</pre>";
