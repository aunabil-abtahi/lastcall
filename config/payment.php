<?php

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
            "SSLCOMMERZ_STORE_PASSWORD", "SSLCOMMERZ_IPN_URL", "PORT",
            "RAILWAY_PUBLIC_DOMAIN", "RAILWAY_STATIC_URL"
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

function sslcommerzConfig(): array {
    $env = lastCallEnv();
    $mode = strtolower($env["SSLCOMMERZ_MODE"] ?? "sandbox");

    if ($mode !== "sandbox") {
        throw new RuntimeException("Only SSLCOMMERZ Sandbox is enabled for this project.");
    }

    $storeId = trim($env["SSLCOMMERZ_STORE_ID"] ?? "");
    $storePassword = trim($env["SSLCOMMERZ_STORE_PASSWORD"] ?? "");

    // Auto-detect production APP_URL if running in cloud (Railway) or via browser
    $appUrl = trim($env["APP_URL"] ?? "");
    if ($appUrl === "" || str_contains($appUrl, "localhost") && !empty($_SERVER["HTTP_HOST"])) {
        if (!empty($env["RAILWAY_PUBLIC_DOMAIN"])) {
            $appUrl = "https://" . $env["RAILWAY_PUBLIC_DOMAIN"];
        } elseif (!empty($_SERVER["HTTP_HOST"])) {
            $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https") ? "https" : "http";
            $appUrl = $scheme . "://" . $_SERVER["HTTP_HOST"];
        }
    }
    $appUrl = rtrim($appUrl, "/");

    if ($storeId === "" || $storeId === "replace_with_your_sandbox_store_id" || $storePassword === "" || $storePassword === "replace_with_your_sandbox_store_password") {
        throw new RuntimeException("SSLCOMMERZ Sandbox credentials are not configured. Please set SSLCOMMERZ_STORE_ID and SSLCOMMERZ_STORE_PASSWORD.");
    }

    if ($appUrl === "") {
        throw new RuntimeException("APP_URL configuration is incomplete.");
    }

    return [
        "store_id" => $storeId,
        "store_password" => $storePassword,
        "app_url" => $appUrl,
        "ipn_url" => trim($env["SSLCOMMERZ_IPN_URL"] ?? ""),
        "init_url" => "https://sandbox.sslcommerz.com/gwprocess/v4/api.php",
        "validation_url" => "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php"
    ];
}
