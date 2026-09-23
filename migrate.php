<?php
require_once __DIR__ . '/config/database.php';

echo "<h1>Database Migration Tool</h1>";

$files = [
    __DIR__ . '/database/schema.sql',
    __DIR__ . '/database/views.sql',
    __DIR__ . '/database/seed.sql'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<h3>Executing: " . basename($file) . "</h3>";
        $sql = file_get_contents($file);
        
        try {
            // Execute the entire file as a single batch
            $pdo->exec($sql);
            echo "<p style='color: green;'>Successfully executed " . basename($file) . "</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error executing " . basename($file) . ": " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>File not found: " . basename($file) . "</p>";
    }
}

echo "<h2>Migration Complete!</h2>";
echo "<p>Please delete this file (migrate.php) after use for security.</p>";
?>
