<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

if (!isLoggedIn() || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}
require_csrf();

$sellerId = filter_input(INPUT_POST, "seller_id", FILTER_VALIDATE_INT);
$listingId = filter_input(INPUT_POST, "listing_id", FILTER_VALIDATE_INT) ?: 0;
$returnTo = trim($_POST["return_to"] ?? "");

if ($sellerId && $sellerId !== (int) $_SESSION["user_id"]) {
    $check = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'seller'");
    $check->execute([$sellerId]);
    if ($check->fetchColumn()) {
        $exists = $pdo->prepare("SELECT 1 FROM followed_sellers WHERE follower_id = ? AND seller_id = ?");
        $exists->execute([$_SESSION["user_id"], $sellerId]);
        if ($exists->fetchColumn()) {
            $pdo->prepare("DELETE FROM followed_sellers WHERE follower_id = ? AND seller_id = ?")->execute([$_SESSION["user_id"], $sellerId]);
        } else {
            $pdo->prepare("INSERT INTO followed_sellers (follower_id, seller_id) VALUES (?, ?)")->execute([$_SESSION["user_id"], $sellerId]);
        }
    }
}

if ($returnTo === "followed_sellers.php") {
    header("Location: followed_sellers.php");
} elseif ($listingId > 0) {
    header("Location: listing.php?id=" . $listingId);
} else {
    header("Location: index.php");
}
exit;
