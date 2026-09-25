<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$commentId = $data['comment_id'] ?? 0;
$newContent = trim($data['content'] ?? '');
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

if (!$commentId || !$newContent) {
    echo json_encode(['success' => false, 'error' => 'Missing comment ID or content']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT author_id FROM community_comments WHERE comment_id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }

    if ($comment['author_id'] != $userId && $userRole !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE community_comments SET content = ? WHERE comment_id = ?");
    $updateStmt->execute([$newContent, $commentId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
