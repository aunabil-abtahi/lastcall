<?php
echo "<h1>Environment Variable Diagnostic</h1>";
echo "<p>Checking to see what variables Railway is providing to PHP.</p>";

$keysToCheck = [
    "MYSQL_URL", "MYSQLHOST", "MYSQLPORT", "MYSQLDATABASE", "MYSQLUSER", 
    "DB_HOST", "DATABASE_URL"
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Variable Name</th><th>Status</th></tr>";

foreach ($keysToCheck as $key) {
    $val1 = getenv($key);
    $val2 = $_ENV[$key] ?? false;
    $val3 = $_SERVER[$key] ?? false;
    
    $exists = ($val1 !== false || $val2 !== false || $val3 !== false);
    
    if ($exists) {
        echo "<tr><td><strong>$key</strong></td><td style='color:green;'>Found! (Data is hidden for security)</td></tr>";
    } else {
        echo "<tr><td>$key</td><td style='color:red;'>Not Found / Missing</td></tr>";
    }
}
echo "</table>";

echo "<h2>How to fix if they are missing:</h2>";
echo "<ol>";
echo "<li>Go to your Railway dashboard.</li>";
echo "<li>Click on your <strong>lastcall</strong> web app block (the bottom one).</li>";
echo "<li>Click the <strong>Variables</strong> tab at the top.</li>";
echo "<li>Click <strong>New Variable</strong> -> <strong>Reference</strong>.</li>";
echo "<li>Select <strong>MYSQL_URL</strong> from the dropdown.</li>";
echo "<li>Wait for the new deployment to finish.</li>";
echo "</ol>";
?>
