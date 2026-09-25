<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($postId === 0) {
    die("Invalid Post ID.");
}

// Fetch current post
$stmt = $pdo->prepare("SELECT title, content, topic, created_at, updated_at, is_edited FROM community_posts WHERE post_id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    die("Post not found.");
}

// Fetch edit history
$stmtHist = $pdo->prepare("SELECT * FROM community_post_edits WHERE post_id = ? ORDER BY edited_at DESC");
$stmtHist->execute([$postId]);
$edits = $stmtHist->fetchAll();

$_title = "Edit History";
include '../includes/header.php';
?>

<style>
    .history-container { max-width: 700px; margin: 40px auto; padding: 24px; }
    .history-card { background: white; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--border-subtle); margin-bottom: 24px; padding: 20px; }
    .history-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px; margin-bottom: 16px; }
    .history-time { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
    .history-topic { font-size: 0.8rem; padding: 4px 10px; border-radius: 12px; background: var(--surface-muted); color: var(--text-secondary); font-weight: 700; text-transform: uppercase; }
    .history-title { font-size: 1.25rem; font-weight: 800; color: var(--brand-navy); margin: 0 0 12px 0; }
    .history-content { color: var(--text-primary); font-size: 1rem; line-height: 1.6; white-space: pre-wrap; }
    .history-badge { background: var(--brand-navy-tint); color: var(--brand-navy); padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
</style>

<div class="history-container">
    <a href="post.php?id=<?= $postId ?>" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 24px; font-weight: 600;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Post
    </a>

    <h2 style="color: var(--brand-navy); margin-top: 0; margin-bottom: 24px;">Edit History</h2>

    <?php if (empty($edits)): ?>
        <div style="text-align: center; color: var(--text-muted); padding: 40px; background: white; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle);">
            This post has not been edited.
        </div>
    <?php else: ?>
        
        <!-- Current Version -->
        <div class="history-card">
            <div class="history-header">
                <div class="history-time">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Current Version
                </div>
                <div class="history-badge">LATEST</div>
            </div>
            <div style="margin-bottom: 12px;"><span class="history-topic"><?= e($post['topic']) ?></span></div>
            <h3 class="history-title"><?= e($post['title']) ?></h3>
            <div class="history-content"><?= e($post['content']) ?></div>
        </div>

        <!-- Previous Versions -->
        <?php foreach ($edits as $edit): ?>
            <div class="history-card" style="opacity: 0.8;">
                <div class="history-header">
                    <div class="history-time">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?= date('M j, Y \a\t g:i A', strtotime($edit['edited_at'])) ?>
                    </div>
                </div>
                <div style="margin-bottom: 12px;"><span class="history-topic"><?= e($edit['old_topic']) ?></span></div>
                <h3 class="history-title"><?= e($edit['old_title']) ?></h3>
                <div class="history-content"><?= e($edit['old_content']) ?></div>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
