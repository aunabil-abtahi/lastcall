<?php

date_default_timezone_set("Asia/Dhaka");

if (!function_exists("lastCallEnv")) {
    function lastCallEnv(): array {
        static $environment = null;

        if ($environment !== null) {
            return $environment;
        }

        $envPath = __DIR__ . "/../.env";
        $fileEnv = is_file($envPath)
            ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
            : [];

        $systemEnv = [];
        $cloudKeys = [
            "DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS",
            "MYSQLHOST", "MYSQLPORT", "MYSQLDATABASE", "MYSQLUSER", "MYSQLPASSWORD", "MYSQL_URL",
            "APP_URL", "SSLCOMMERZ_MODE", "SSLCOMMERZ_STORE_ID",
            "SSLCOMMERZ_STORE_PASSWORD", "SSLCOMMERZ_IPN_URL", "PORT"
        ];
        foreach ($cloudKeys as $key) {
            $val = getenv($key);
            if ($val !== false) {
                $systemEnv[$key] = $val;
            } elseif (isset($_ENV[$key])) {
                $systemEnv[$key] = $_ENV[$key];
            }
        }

        $environment = array_merge($fileEnv, $systemEnv);
        return $environment;
    }
}

$env = lastCallEnv();

// Support Railway MYSQL_URL connection string if present
$dbUrl = $env["MYSQL_URL"] ?? null;
if (!empty($dbUrl)) {
    $parsedUrl = parse_url($dbUrl);
    $host = $parsedUrl["host"] ?? "localhost";
    $port = $parsedUrl["port"] ?? "3306";
    $username = $parsedUrl["user"] ?? "root";
    $password = $parsedUrl["pass"] ?? "";
    $dbname = ltrim($parsedUrl["path"] ?? "/lastcall", "/");
} else {
    $host = $env["DB_HOST"] ?? $env["MYSQLHOST"] ?? "localhost";
    $port = $env["DB_PORT"] ?? $env["MYSQLPORT"] ?? "3306";
    $dbname = $env["DB_NAME"] ?? $env["MYSQLDATABASE"] ?? "lastcall";
    $username = $env["DB_USER"] ?? $env["MYSQLUSER"] ?? "root";
    $password = $env["DB_PASS"] ?? $env["MYSQLPASSWORD"] ?? "";
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("SET time_zone = '+06:00';");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A database connection error occurred. Please check that MySQL is running.");
}