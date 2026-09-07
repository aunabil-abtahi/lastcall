<?php

date_default_timezone_set("Asia/Dhaka");

if (!function_exists("lastCallEnv")) {
    function lastCallEnv(): array {
        static $environment = null;

        if ($environment !== null) {
            return $environment;
        }

        $envPath = __DIR__ . "/../.env";
        $environment = is_file($envPath)
            ? (parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [])
            : [];

        return $environment;
    }
}

$env = lastCallEnv();
$host = $env["DB_HOST"] ?? "localhost";
$dbname = $env["DB_NAME"] ?? "lastcall";
$username = $env["DB_USER"] ?? "root";
$password = $env["DB_PASS"] ?? "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+06:00';");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A database connection error occurred. Please check that MySQL is running.");
}