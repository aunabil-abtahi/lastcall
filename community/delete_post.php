<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    http_response_code(403);
    echo JSON_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$postId = $data['post_id'] ?? 0;
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = currentUserRole();

// Check if user is author or admin
$stmt = $pdo->prepare("SELECT author_id, image_url FROM community_posts WHERE post_id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
}

if ($post['author_id'] != $userId && $userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this post.']);
    exit;
}

// Delete post
$stmt = $pdo->prepare("DELETE FROM community_posts WHERE post_id = ?");
$stmt->execute([$postId]);

// Optionally delete the image file from server if it exists
if (!empty($post['image_url'])) {
    $imagePath = __DIR__ . '/../' . $post['image_url'];
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
}

echo json_encode(['success' => true]);
