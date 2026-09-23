<?php
$sqlContent = file_get_contents(__DIR__ . '/database/schema.sql');
$statements = array_filter(array_map('trim', explode(';', $sqlContent)));
echo "<pre>";
foreach ($statements as $index => $stmtStr) {
    echo "--- STATEMENT $index ---\n";
    echo htmlspecialchars(substr($stmtStr, 0, 100)) . "\n";
    if ($index > 3) break;
}
echo "</pre>";
