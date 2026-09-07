<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/csrf.php";

function isLoggedIn(): bool {
    return isset($_SESSION["user_id"]);
}

function currentUserName(): string {
    return $_SESSION["full_name"] ?? "";
}

function currentUserRole(): string {
    return $_SESSION["role"] ?? "";
}

function loginPath(): string {
    $script = $_SERVER["SCRIPT_NAME"] ?? "";
    if (preg_match('#/(admin|seller|payments|tasks)/#', $script)) {
        return "../login.php";
    }
    return "login.php";
}

function homePath(): string {
    $script = $_SERVER["SCRIPT_NAME"] ?? "";
    if (preg_match('#/(admin|seller|payments|tasks)/#', $script)) {
        return "../index.php";
    }
    return "index.php";
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: " . loginPath());
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if (currentUserRole() !== $role) {
        header("Location: " . homePath());
        exit;
    }
}