<?php
session_start();
require_once __DIR__ . '/../config/database.php';


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$postId = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT);
if (!$postId) {
    echo json_encode(['error' => 'Invalid post ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.profile_picture 
        FROM community_likes cl
        JOIN users u ON cl.user_id = u.user_id
        WHERE cl.post_id = ?
        ORDER BY cl.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$postId]);
    $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'likes' => $likes]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
