<?php

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

function sslcommerzConfig(): array {
    $env = lastCallEnv();
    $mode = strtolower($env["SSLCOMMERZ_MODE"] ?? "sandbox");

    if ($mode !== "sandbox") {
        throw new RuntimeException("Only SSLCOMMERZ Sandbox is enabled for this project.");
    }

    $storeId = trim($env["SSLCOMMERZ_STORE_ID"] ?? "");
    $storePassword = trim($env["SSLCOMMERZ_STORE_PASSWORD"] ?? "");
    $appUrl = rtrim(trim($env["APP_URL"] ?? ""), "/");

    if ($storeId === "" || $storePassword === "" || $appUrl === "") {
        throw new RuntimeException("SSLCOMMERZ Sandbox configuration is incomplete.");
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
