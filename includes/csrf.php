<?php
/**
 * CSRF Protection Framework for LastCall
 * Provides cryptographically secure token generation and validation against Cross-Site Request Forgery.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retrieve or generate the current session's CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

/**
 * Return an HTML hidden input tag containing the CSRF token.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify a submitted CSRF token against the session token using timing-safe comparison.
 */
function csrf_verify(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST["csrf_token"] ?? $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    }

    if (empty($token) || empty($_SESSION["csrf_token"])) {
        return false;
    }

    return hash_equals($_SESSION["csrf_token"], (string) $token);
}

/**
 * Enforce CSRF verification on incoming POST requests.
 * Terminates execution with HTTP 403 Forbidden if the token is absent or invalid.
 */
function require_csrf(): void {
    if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
        if (!csrf_verify()) {
            http_response_code(403);

            // Handle JSON/AJAX requests
            $isJson = (
                (isset($_SERVER["HTTP_ACCEPT"]) && str_contains($_SERVER["HTTP_ACCEPT"], "application/json")) ||
                (isset($_SERVER["CONTENT_TYPE"]) && str_contains($_SERVER["CONTENT_TYPE"], "application/json"))
            );

            if ($isJson) {
                header("Content-Type: application/json; charset=utf-8");
                echo json_encode([
                    "success" => false,
                    "error" => "Invalid or expired security token (CSRF). Please refresh and try again."
                ]);
                exit;
            }

            // HTML error response
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>403 Forbidden | LastCall</title>
                <style>
                    body {
                        margin: 0;
                        padding: 60px 20px;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: #f8fafc;
                        color: #0f172a;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 80vh;
                    }
                    .card {
                        background: #ffffff;
                        border: 1px solid #e2e8f0;
                        border-radius: 16px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                        padding: 40px 32px;
                        max-width: 480px;
                        text-align: center;
                    }
                    .icon {
                        font-size: 48px;
                        margin-bottom: 16px;
                    }
                    h1 {
                        font-size: 22px;
                        margin: 0 0 10px;
                        color: #0f172a;
                    }
                    p {
                        color: #64748b;
                        font-size: 14px;
                        line-height: 1.5;
                        margin: 0 0 24px;
                    }
                    .btn {
                        display: inline-block;
                        background: #f97316;
                        color: #ffffff;
                        font-weight: 600;
                        text-decoration: none;
                        padding: 10px 20px;
                        border-radius: 8px;
                        font-size: 14px;
                    }
                    .btn:hover {
                        background: #ea580c;
                    }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="icon">🛡️</div>
                    <h1>Security Token Error</h1>
                    <p>Your request could not be authenticated due to an invalid or expired CSRF security token. Please refresh the page and submit again.</p>
                    <a href="javascript:history.back()" class="btn">&larr; Return to Previous Page</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
}
