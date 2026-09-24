<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

$loggedIn = isLoggedIn();
$userId = currentUserId();
$postId = (int)($_GET['id'] ?? 0);

if (!$postId) {
    header("Location: index.php");
    exit;
}

// Fetch the main post
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.full_name as author_name,
        u.role as author_role
    FROM community_posts p
    JOIN users u ON p.author_id = u.user_id
    WHERE p.post_id = ?
");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: index.php");
    exit;
}

// Handle Comment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn) {
    verifyCsrfToken();
    $content = trim($_POST['content'] ?? '');

    if ($content) {
        $stmt = $pdo->prepare("INSERT INTO community_comments (post_id, author_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$postId, $userId, $content]);
        $_SESSION['flash_success'] = "Reply posted successfully!";
        header("Location: post.php?id=" . $postId);
        exit;
    }
}

// Fetch Comments
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        u.full_name as author_name,
        u.role as author_role
    FROM community_comments c
    JOIN users u ON c.author_id = u.user_id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$postId]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

function getTopicColor($topic) {
    return match($topic) {
        'food' => 'var(--category-meal-bg)',
        'events' => 'var(--category-beverage-bg)',
        'feedback' => '#fef3c7',
        default => '#f1f5f9'
    };
}
function getTopicTextColor($topic) {
    return match($topic) {
        'food' => 'var(--category-meal-text)',
        'events' => 'var(--category-beverage-text)',
        'feedback' => '#b45309',
        default => '#475569'
    };
}

$pageTitle = e($post['title']) . " | LastCall Community";
require_once __DIR__ . "/../includes/header.php";
?>

<main style="max-width: 800px; margin: 0 auto; padding: 32px 16px;">
    <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 24px; font-weight: 500;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Community Hub
    </a>

    <!-- Main Post -->
    <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
        <span class="badge" style="background: <?= getTopicColor($post['topic']) ?>; color: <?= getTopicTextColor($post['topic']) ?>; margin-bottom: 12px; display: inline-block;">
            <?= e(ucfirst($post['topic'])) ?>
        </span>
        <h1 style="font-size: 1.8rem; color: var(--brand-navy); margin: 0 0 16px 0;"><?= e($post['title']) ?></h1>
        
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-subtle);">
            <div style="width: 40px; height: 40px; background: var(--brand-navy); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem;">
                <?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                    <?= e($post['author_name']) ?>
                    <?php if ($post['author_role'] === 'seller' || $post['author_role'] === 'admin'): ?>
                        <span class="badge" style="font-size: 0.65rem; padding: 2px 6px;"><?= ucfirst($post['author_role']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Posted on <?= date('F j, Y \a\t g:i A', strtotime($post['created_at'])) ?>
                </div>
            </div>
        </div>

        <div style="color: var(--text-main); line-height: 1.6; font-size: 1.05rem; white-space: pre-wrap;"><?= e($post['content']) ?></div>
    </div>

    <!-- Comments Section -->
    <div style="margin-bottom: 40px;">
        <h3 style="font-size: 1.25rem; color: var(--brand-navy); margin-bottom: 16px;">
            <?= count($comments) ?> <?= count($comments) === 1 ? 'Reply' : 'Replies' ?>
        </h3>

        <?php if (!empty($comments)): ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($comments as $comment): ?>
                    <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <div style="width: 32px; height: 32px; background: #e2e8f0; color: var(--brand-navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;">
                                <?= mb_strtoupper(mb_substr($comment['author_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--text-main); font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">
                                    <?= e($comment['author_name']) ?>
                                    <?php if ($comment['author_role'] === 'seller' || $comment['author_role'] === 'admin'): ?>
                                        <span class="badge" style="font-size: 0.6rem; padding: 1px 4px;"><?= ucfirst($comment['author_role']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($comment['author_id'] === $post['author_id']): ?>
                                        <span class="badge" style="background: var(--brand-coral); color: white; font-size: 0.6rem; padding: 1px 4px;">Author</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <div style="color: var(--text-main); line-height: 1.5; font-size: 0.95rem; white-space: pre-wrap;"><?= e($comment['content']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reply Form -->
    <?php if ($loggedIn): ?>
        <div style="background: #f8fafc; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px;">
            <h4 style="font-size: 1.1rem; color: var(--brand-navy); margin-bottom: 12px;">Leave a Reply</h4>
            <form method="POST" action="post.php?id=<?= $postId ?>">
                <?= csrf_field() ?>
                <div style="margin-bottom: 12px;">
                    <textarea name="content" rows="4" placeholder="Write your reply here..." required class="form-input" style="width: 100%; resize: vertical;"></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="primary-button" style="padding: 8px 20px;">Post Reply</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div style="background: #f8fafc; border: 1px dashed var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; text-align: center;">
            <p style="color: var(--text-secondary); margin-bottom: 12px;">Log in to reply to this discussion.</p>
            <a href="../login.php" class="primary-button" style="display: inline-block;">Log In</a>
        </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
