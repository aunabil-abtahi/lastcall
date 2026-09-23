<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/database.php';

echo "<h1>Force Migration Tool</h1>";

try {
    // Drop all tables first to start fresh
    $pdo->query('SET foreign_key_checks = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->query("DROP TABLE IF EXISTS `$table`");
        echo "Dropped table: $table<br>";
    }
    
    // Also drop views
    $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($views as $view) {
        $pdo->query("DROP VIEW IF EXISTS `$view`");
        echo "Dropped view: $view<br>";
    }
    $pdo->query('SET foreign_key_checks = 1');
    echo "<hr>";
} catch (Exception $e) {
    echo "Error dropping tables: " . $e->getMessage() . "<br>";
}

$files = [
    __DIR__ . '/database/schema.sql',
    __DIR__ . '/database/views.sql',
    __DIR__ . '/database/seed.sql'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<h3>Executing: " . basename($file) . "</h3>";
        $sqlContent = file_get_contents($file);
        
        // Split by statement manually to pinpoint the exact failure
        $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
        
        $success = true;
        foreach ($statements as $index => $stmtStr) {
            if (empty($stmtStr)) continue;
            try {
                $pdo->exec($stmtStr);
            } catch (PDOException $e) {
                echo "<p style='color: red;'>Error at statement #" . ($index + 1) . " in " . basename($file) . ": " . $e->getMessage() . "</p>";
                echo "<pre>" . htmlspecialchars(substr($stmtStr, 0, 200)) . "...</pre>";
                $success = false;
                break; // Stop executing further if one fails
            }
        }
        
        if ($success) {
            echo "<p style='color: green;'>Successfully executed " . basename($file) . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>File not found: " . basename($file) . "</p>";
    }
}
?>
