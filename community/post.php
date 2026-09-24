<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

$loggedIn = isLoggedIn();
$userId = $_SESSION['user_id'] ?? 0;
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
    require_csrf();
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

<style>
    .post-detail-container { background: var(--surface-card); border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 32px; }
    .post-detail-header { padding: 32px; border-bottom: 1px solid var(--border-subtle); }
    .post-detail-topic { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; }
    .post-detail-title { font-size: 2rem; color: var(--brand-navy); margin: 0 0 24px 0; font-family: var(--font-serif); font-weight: 700; line-height: 1.3; }
    
    .post-meta-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
    .author-info { display: flex; align-items: center; gap: 16px; }
    .author-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--brand-navy-light), var(--brand-navy)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; box-shadow: var(--shadow-sm); }
    .author-name { font-weight: 700; color: var(--text-primary); font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
    .author-role-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
    .author-role-seller { background: var(--brand-emerald-tint); color: var(--brand-emerald-dark); }
    .author-role-admin { background: var(--brand-coral-tint); color: var(--brand-coral-hover); }
    .post-timestamp { font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 4px; }
    
    .post-detail-content { padding: 32px; font-size: 1.1rem; line-height: 1.7; color: var(--text-primary); }
    
    .comments-section { padding: 0 16px; }
    .comments-header { font-size: 1.25rem; font-weight: 700; color: var(--brand-navy); margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
    
    .comment-card { background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 16px; transition: var(--transition-fast); }
    .comment-card:hover { border-color: var(--border-medium); box-shadow: var(--shadow-sm); }
    .comment-card.is-author { border-left: 4px solid var(--brand-coral); }
    
    .comment-header { display: flex; justify-content: space-between; margin-bottom: 12px; }
    .comment-author-info { display: flex; align-items: center; gap: 12px; }
    .comment-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--surface-muted); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; }
    .comment-author { font-weight: 600; color: var(--text-primary); font-size: 0.95rem; display: flex; align-items: center; gap: 6px; }
    .comment-time { font-size: 0.8rem; color: var(--text-muted); }
    .comment-content { font-size: 1rem; line-height: 1.6; color: var(--text-secondary); padding-left: 48px; }
    
    .reply-box { background: var(--surface-bg); border: 1px solid var(--border-subtle); border-radius: var(--radius-xl); padding: 24px; margin-top: 32px; }
    .reply-box h4 { font-size: 1.1rem; color: var(--brand-navy); margin-bottom: 16px; font-weight: 600; }
    
    .post-options { position: relative; margin-left: auto; }
    .post-options-btn { background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: var(--transition-fast); }
    .post-options-btn:hover { background: var(--surface-muted); color: var(--text-primary); }
    .post-dropdown { position: absolute; right: 0; top: 100%; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); min-width: 150px; z-index: 10; display: none; overflow: hidden; }
    .post-dropdown.show { display: block; }
    .post-dropdown-item { display: flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--text-primary); cursor: pointer; border: none; background: transparent; width: 100%; text-align: left; font-size: 0.95rem; }
    .post-dropdown-item:hover { background: var(--surface-muted); }
    .post-dropdown-item.danger { color: var(--brand-coral); }
</style>

<main style="max-width: 900px; margin: 0 auto; padding: 40px 16px; width: 100%; box-sizing: border-box;">
    <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); text-decoration: none; margin-bottom: 24px; font-weight: 600; transition: var(--transition-fast);" onmouseover="this.style.color='var(--brand-navy)'" onmouseout="this.style.color='var(--text-secondary)'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Community Hub
    </a>

    <!-- Main Post -->
    <article class="post-detail-container">
        <div class="post-detail-header">
            <div class="post-detail-topic" style="background: <?= getTopicColor($post['topic']) ?>; color: <?= getTopicTextColor($post['topic']) ?>;">
                <?= e(ucfirst($post['topic'])) ?>
            </div>
            
            <h1 class="post-detail-title"><?= e($post['title']) ?></h1>
            
            <div class="post-meta-bar">
                <div class="author-info">
                    <div class="author-avatar">
                        <?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="author-name">
                            <?= e($post['author_name']) ?>
                            <?php if ($post['author_role'] === 'seller'): ?>
                                <span class="author-role-badge author-role-seller">Vendor</span>
                            <?php elseif ($post['author_role'] === 'admin'): ?>
                                <span class="author-role-badge author-role-admin">Admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="post-timestamp">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <?= date('F j, Y \a\t g:i A', strtotime($post['created_at'])) ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($loggedIn): ?>
                <div class="post-options">
                    <button class="post-options-btn" onclick="toggleDropdown(<?= $post['post_id'] ?>)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                    </button>
                    <div class="post-dropdown" id="dropdown_<?= $post['post_id'] ?>">
                        <?php if ($post['author_id'] == $userId || ($_SESSION['role'] ?? '') === 'admin'): ?>
                            <button class="post-dropdown-item danger" onclick="deletePost(<?= $post['post_id'] ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                Delete Post
                            </button>
                        <?php else: ?>
                            <button class="post-dropdown-item" onclick="reportPost(<?= $post['post_id'] ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                Report Post
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($post['image_url'])): ?>
            <img src="<?= $_base . e($post['image_url']) ?>" alt="Post attached image" style="width: 100%; max-height: 600px; object-fit: cover; display: block; border-bottom: 1px solid var(--border-subtle);">
        <?php endif; ?>
        <div class="post-detail-content">
            <?= nl2br(e($post['content'])) ?>
        </div>
    </article>

    <!-- Comments Section -->
    <div class="comments-section">
        <h3 class="comments-header">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--brand-coral);">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <?= count($comments) ?> <?= count($comments) === 1 ? 'Reply' : 'Replies' ?>
        </h3>

        <?php if (!empty($comments)): ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-card <?= $comment['author_id'] === $post['author_id'] ? 'is-author' : '' ?>">
                        <div class="comment-header">
                            <div class="comment-author-info">
                                <div class="comment-avatar">
                                    <?= mb_strtoupper(mb_substr($comment['author_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="comment-author">
                                        <?= e($comment['author_name']) ?>
                                        <?php if ($comment['author_role'] === 'seller'): ?>
                                            <span class="author-role-badge author-role-seller">Vendor</span>
                                        <?php elseif ($comment['author_role'] === 'admin'): ?>
                                            <span class="author-role-badge author-role-admin">Admin</span>
                                        <?php endif; ?>
                                        <?php if ($comment['author_id'] === $post['author_id']): ?>
                                            <span class="author-role-badge" style="background: var(--brand-coral); color: white;">Author</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-time">
                                        <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="comment-content">
                            <?= nl2br(e($comment['content'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-medium);">
                <p style="color: var(--text-muted); font-size: 1.05rem;">No replies yet. Be the first to chime in!</p>
            </div>
        <?php endif; ?>

        <!-- Reply Form -->
        <?php if ($loggedIn): ?>
            <div class="reply-box">
                <h4>Write a Reply</h4>
                <form method="POST" action="post.php?id=<?= $postId ?>">
                    <?= csrf_field() ?>
                    <div style="margin-bottom: 16px;">
                        <textarea name="content" rows="4" placeholder="Share your thoughts..." required class="form-input" style="font-size: 1rem; padding: 16px; resize: vertical; width: 100%; box-sizing: border-box;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-primary-navy" style="border-radius: 30px; padding: 10px 24px; font-weight: 600;">Post Reply</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="reply-box" style="text-align: center; border-style: dashed;">
                <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 1.05rem;">Log in to participate in this discussion.</p>
                <a href="../login.php" class="btn-primary-navy" style="border-radius: 30px; padding: 10px 32px; display: inline-block;">Log In</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
function toggleDropdown(postId) {
    const dropdown = document.getElementById('dropdown_' + postId);
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.post-options')) {
        document.querySelectorAll('.post-dropdown').forEach(el => el.classList.remove('show'));
    }
});

async function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) return;
    
    try {
        const response = await fetch('delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
            },
            body: JSON.stringify({ post_id: postId })
        });
        
        const data = await response.json();
        if (data.success) {
            window.location.href = 'index.php';
        } else {
            alert(data.error || 'Failed to delete post.');
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred.');
    }
}

async function reportPost(postId) {
    const reason = prompt("Why are you reporting this post?");
    if (!reason) return;
    
    try {
        const response = await fetch('report_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
            },
            body: JSON.stringify({ post_id: postId, reason: reason })
        });
        
        const data = await response.json();
        if (data.success) {
            alert("Post reported successfully. Our admins will review it.");
        } else {
            alert(data.error || 'Failed to report post.');
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred.');
    }
}
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
