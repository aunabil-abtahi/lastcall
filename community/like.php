<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF Token Verification Failed']);
    exit;
}

$postId = (int)($data['post_id'] ?? 0);

if (!$postId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Check if like exists
    $stmt = $pdo->prepare("SELECT 1 FROM community_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $liked = (bool)$stmt->fetchColumn();

    if ($liked) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM community_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $action = 'unliked';
    } else {
        // Like
        $stmt = $pdo->prepare("INSERT INTO community_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$postId, $userId]);
        $action = 'liked';
    }

    // Get new count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $count = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'action' => $action,
        'likes' => $count
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
