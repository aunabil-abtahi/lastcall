<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$loggedIn = isLoggedIn();
if (!$loggedIn) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? 0;
$postId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0);

if ($postId === 0) {
    die("Invalid Post ID.");
}

// Fetch post
$stmt = $pdo->prepare("SELECT * FROM community_posts WHERE post_id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    die("Post not found.");
}

if ($post['author_id'] != $userId) {
    die("You do not have permission to edit this post.");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $topic = trim($_POST['topic'] ?? '');

    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        $pdo->beginTransaction();
        try {
            // Insert into edit history
            $stmtHist = $pdo->prepare("INSERT INTO community_post_edits (post_id, old_title, old_content, old_topic) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([$postId, $post['title'], $post['content'], $post['topic']]);

            // Update post
            $stmtUpd = $pdo->prepare("UPDATE community_posts SET title = ?, content = ?, topic = ?, is_edited = 1 WHERE post_id = ?");
            $stmtUpd->execute([$title, $content, $topic, $postId]);

            $pdo->commit();
            header("Location: post.php?id=" . $postId);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "An error occurred while updating the post.";
        }
    }
}

$_title = "Edit Post";
include '../includes/header.php';
?>

<style>
    .edit-container { max-width: 800px; width: 90%; margin: 40px auto; padding: 32px; background: white; border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); border: 1px solid var(--border-subtle); box-sizing: border-box; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--brand-navy); font-size: 0.95rem; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; box-sizing: border-box; padding: 14px 16px; border: 1px solid var(--border-medium); border-radius: 12px; font-size: 1rem; outline: none; background: var(--surface-card); transition: all 0.2s ease; }
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--brand-navy-light); box-shadow: 0 0 0 3px var(--brand-navy-tint); background: white; }
    
    .btn-action { padding: 12px 24px; border-radius: 30px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s ease; text-decoration: none; border: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-cancel { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-medium); }
    .btn-cancel:hover { background: var(--surface-muted); color: var(--text-primary); }
    .btn-save { background: var(--brand-navy); color: white; box-shadow: var(--shadow-sm); }
    .btn-save:hover { background: var(--brand-navy-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
    
    @media (max-width: 600px) {
        .edit-container { padding: 20px; margin: 20px auto; border-radius: var(--radius-lg); }
        .action-buttons { flex-direction: column-reverse; }
        .btn-action { width: 100%; }
    }
</style>

<div class="edit-container">
    <h2 style="color: var(--brand-navy); margin-top: 0; margin-bottom: 24px;">Edit Post</h2>
    
    <?php if ($error): ?>
        <div style="background: var(--brand-coral-tint); color: var(--brand-coral-hover); padding: 12px; border-radius: 8px; margin-bottom: 16px;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_post.php">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= $postId ?>">

        <div class="form-group">
            <label>Topic</label>
            <select name="topic" required>
                <option value="general" <?= $post['topic'] === 'general' ? 'selected' : '' ?>>💬 General</option>
                <option value="food" <?= $post['topic'] === 'food' ? 'selected' : '' ?>>🍔 Food Rescue</option>
                <option value="events" <?= $post['topic'] === 'events' ? 'selected' : '' ?>>🎟️ Event Hype</option>
                <option value="feedback" <?= $post['topic'] === 'feedback' ? 'selected' : '' ?>>💡 Feedback</option>
            </select>
        </div>

        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="<?= e($post['title']) ?>" required>
        </div>

        <div class="form-group">
            <label>Content</label>
            <textarea name="content" rows="6" required><?= e($post['content']) ?></textarea>
        </div>

        <div class="action-buttons" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 32px;">
            <a href="post.php?id=<?= $postId ?>" class="btn-action btn-cancel">Cancel</a>
            <button type="submit" class="btn-action btn-save">Save Changes</button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
