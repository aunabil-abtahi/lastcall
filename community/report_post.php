<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$postId = $data['post_id'] ?? 0;
$reason = trim($data['reason'] ?? '');
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!$postId || empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Post ID and reason are required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Check if user already reported this post
$stmt = $pdo->prepare("SELECT 1 FROM reports WHERE reported_by = ? AND post_id = ?");
$stmt->execute([$userId, $postId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'You have already reported this post.']);
    exit;
}

// Check if the post exists
$stmt = $pdo->prepare("SELECT 1 FROM community_posts WHERE post_id = ?");
$stmt->execute([$postId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
}

// Create report
// We assume the schema has been updated to support `post_id` in `reports`
$stmt = $pdo->prepare("INSERT INTO reports (reported_by, post_id, reason) VALUES (?, ?, ?)");
$stmt->execute([$userId, $postId, $reason]);

echo json_encode(['success' => true]);
