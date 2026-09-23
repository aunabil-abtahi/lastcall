<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/database.php';

echo "<h1>Database Re-Migration Tool</h1>";

$files = [
    __DIR__ . '/database/schema.sql',
    __DIR__ . '/database/views.sql',
    __DIR__ . '/database/seed.sql'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<h3>Executing: " . basename($file) . "</h3>";
        $sql = file_get_contents($file);
        
        // Split by semicolon, but this is naive and might break if semicolons are inside strings.
        // A better approach for schema.sql is to execute it. If PDO multi-query is failing, 
        // we can enable it explicitly.
        
        try {
            // Enable multi-statements explicitly just in case
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            do {
                // Consume all result sets to ensure any errors in subsequent statements are caught
            } while ($stmt->nextRowset());
            
            echo "<p style='color: green;'>Successfully executed " . basename($file) . "</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error executing " . basename($file) . ": " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>File not found: " . basename($file) . "</p>";
    }
}
?>
