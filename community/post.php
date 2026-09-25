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

$currentUser = null;
if ($loggedIn) {
    $stmt = $pdo->prepare("SELECT full_name, profile_picture FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch the main post
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.full_name as author_name,
        u.role as author_role,
        u.profile_picture as author_profile_picture,
        (SELECT COUNT(*) FROM community_likes cl WHERE cl.post_id = p.post_id) as like_count,
        (SELECT 1 FROM community_likes cl WHERE cl.post_id = p.post_id AND cl.user_id = ?) as user_liked
    FROM community_posts p
    JOIN users u ON p.author_id = u.user_id
    WHERE p.post_id = ?
");
$stmt->execute([$userId, $postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: index.php");
    exit;
}

// Handle Comment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn) {
    require_csrf();
    $content = trim($_POST['content'] ?? '');
    $parentId = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);
    if (!$parentId) $parentId = null;

    if ($content) {
        $stmt = $pdo->prepare("INSERT INTO community_comments (post_id, parent_id, author_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$postId, $parentId, $userId, $content]);
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
        u.role as author_role,
        u.profile_picture as author_profile_picture
    FROM community_comments c
    JOIN users u ON c.author_id = u.user_id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$postId]);
$allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$comments = [];
$replies = [];
foreach($allComments as $c) {
    if ($c['parent_id']) {
        $replies[$c['parent_id']][] = $c;
    } else {
        $comments[] = $c;
    }
}

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
    
    .fb-comment-wrapper { display: flex; gap: 12px; margin-top: 24px; align-items: flex-start; }
    .fb-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: var(--surface-muted); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0; color: var(--text-secondary); }
    .fb-input-container { flex: 1; background: var(--surface-muted); border-radius: 20px; display: flex; position: relative; transition: background 0.2s; border: 1px solid transparent; }
    .fb-input-container:focus-within { background: white; border-color: var(--brand-navy-light); box-shadow: 0 0 0 2px var(--brand-navy-tint); }
    .fb-input { flex: 1; border: none; background: transparent; padding: 12px 50px 12px 16px; font-size: 0.95rem; font-family: inherit; resize: none; outline: none; box-sizing: border-box; color: var(--text-primary); border-radius: 20px; line-height: 1.4; overflow: hidden; min-height: 44px; }
    .fb-actions { position: absolute; right: 6px; bottom: 6px; display: flex; align-items: center; }
    .fb-send-btn { background: transparent; border: none; color: var(--brand-navy); cursor: pointer; padding: 6px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .fb-send-btn:hover { background: rgba(0,0,0,0.05); }
    .fb-send-btn svg { width: 20px; height: 20px; transform: rotate(45deg); margin-left: -2px; margin-bottom: 2px; }
    
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
                    <div class="author-avatar" style="overflow:hidden;">
                        <?php if (!empty($post['author_profile_picture'])): ?>
                            <img src="<?= $_base . e($post['author_profile_picture']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
                        <?php endif; ?>
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
                            <?php if ($post['is_edited'] ?? false): ?>
                                &middot; <a href="edit_history.php?id=<?= $post['post_id'] ?>" style="color: var(--text-muted); text-decoration: underline;">Edited</a>
                            <?php endif; ?>
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
                            <?php if ($post['author_id'] == $userId): ?>
                                <a href="edit_post.php?id=<?= $post['post_id'] ?>" class="post-dropdown-item" style="color: inherit; text-decoration: none;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    Edit Post
                                </a>
                            <?php endif; ?>
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
        <div class="post-detail-content" style="padding-bottom: 16px;">
            <?= nl2br(e($post['content'])) ?>
        </div>

        <div style="padding: 0 32px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-subtle);">
            <div>
                <a href="#" onclick="showLikesModal(event)" style="color: var(--text-muted); font-size: 0.95rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                    <span id="likeCountDetail"><?= (int)$post['like_count'] ?></span> Likes
                </a>
            </div>
            <div>
                <button class="action-btn like-btn <?= $post['user_liked'] ? 'liked' : '' ?>" data-post-id="<?= $post['post_id'] ?>" style="background: var(--surface-muted); padding: 8px 16px; border-radius: 20px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                    </svg>
                    <span class="like-text"><?= $post['user_liked'] ? 'Liked' : 'Like' ?></span>
                </button>
            </div>
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
                    <div class="comment-card <?= $comment['author_id'] === $post['author_id'] ? 'is-author' : '' ?>" style="margin-bottom: 24px;">
                        <div class="comment-header">
                            <div class="comment-author-info">
                                <div class="comment-avatar" style="overflow:hidden;">
                                    <?php if (!empty($comment['author_profile_picture'])): ?>
                                        <img src="<?= $_base . e($comment['author_profile_picture']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <?= mb_strtoupper(mb_substr($comment['author_name'], 0, 1)) ?>
                                    <?php endif; ?>
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
                            <?php if ($loggedIn): ?>
                            <div style="margin-top: 10px;">
                                <button type="button" class="btn-demo-action clear-btn" style="padding: 2px 8px; font-size: 0.85rem;" onclick="showReplyForm(<?= $comment['comment_id'] ?>)">Reply</button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Replies -->
                        <?php if (isset($replies[$comment['comment_id']])): ?>
                            <div style="margin-left: 48px; margin-top: 16px; border-left: 2px solid var(--border-subtle); padding-left: 16px;">
                                <?php foreach($replies[$comment['comment_id']] as $reply): ?>
                                    <div style="margin-bottom: 16px;">
                                        <div class="comment-author-info" style="margin-bottom: 8px;">
                                            <div class="comment-avatar" style="width: 28px; height: 28px; font-size: 0.8rem; overflow:hidden;">
                                                <?php if (!empty($reply['author_profile_picture'])): ?>
                                                    <img src="<?= $_base . e($reply['author_profile_picture']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                                                <?php else: ?>
                                                    <?= mb_strtoupper(mb_substr($reply['author_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="comment-author" style="font-size: 0.85rem;">
                                                    <?= e($reply['author_name']) ?>
                                                </div>
                                                <div class="comment-time" style="font-size: 0.75rem;">
                                                    <?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="comment-content" style="padding-left: 40px; font-size: 0.95rem;">
                                            <?= nl2br(e($reply['content'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Reply Form (Hidden) -->
                        <div id="reply-form-<?= $comment['comment_id'] ?>" style="display: none; margin-left: 48px; margin-top: 12px;">
                            <form method="POST" action="post.php?id=<?= $postId ?>" style="margin: 0; display: flex; gap: 8px; align-items: flex-start;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="parent_id" value="<?= $comment['comment_id'] ?>">
                                <?php if (isset($currentUser)): ?>
                                <div class="fb-avatar" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                    <?php if (!empty($currentUser['profile_picture'])): ?>
                                        <img src="<?= $_base . e($currentUser['profile_picture']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    <?php else: ?>
                                        <?= mb_strtoupper(mb_substr($currentUser['full_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="fb-input-container" style="border-radius: 16px; background: var(--surface-bg); border: 1px solid var(--border-subtle);">
                                    <input type="text" name="content" required placeholder="Reply to <?= e(explode(' ', $comment['author_name'])[0]) ?>..." class="fb-input" style="min-height: 32px; padding: 8px 40px 8px 12px; font-size: 0.9rem;">
                                    <div class="fb-actions" style="bottom: 2px;">
                                        <button type="submit" class="fb-send-btn" style="padding: 4px;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-medium);">
                <p style="color: var(--text-muted); font-size: 1.05rem;">No replies yet. Be the first to chime in!</p>
            </div>
        <?php endif; ?>

        <!-- Main Comment Form (FB Style) -->
        <?php if ($loggedIn && $currentUser): ?>
            <div class="fb-comment-wrapper">
                <div class="fb-avatar">
                    <?php if (!empty($currentUser['profile_picture'])): ?>
                        <img src="<?= $_base . e($currentUser['profile_picture']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?= mb_strtoupper(mb_substr($currentUser['full_name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <form method="POST" action="post.php?id=<?= $postId ?>" style="margin: 0; flex: 1;">
                    <?= csrf_field() ?>
                    <div class="fb-input-container">
                        <textarea name="content" class="fb-input" placeholder="Comment as <?= e($currentUser['full_name']) ?>..." required oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                        <div class="fb-actions">
                            <button type="submit" class="fb-send-btn" title="Post Comment">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="reply-box" style="text-align: center; border-style: dashed; padding: 40px 20px;">
                <div style="width: 64px; height: 64px; background: var(--surface-muted); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <h4 style="margin: 0 0 8px 0; color: var(--brand-navy); font-size: 1.2rem;">Join the Conversation</h4>
                <p style="color: var(--text-secondary); margin: 0 0 24px 0; font-size: 1rem; max-width: 400px; margin-left: auto; margin-right: auto;">Log in to share your thoughts, ask questions, and connect with the community.</p>
                <a href="../login.php" class="reply-btn" style="text-decoration: none;">Log In to Reply</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<div id="likesModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 100%; max-width: 400px; border-radius: var(--radius-xl); padding: 24px; position: relative;">
        <button onclick="document.getElementById('likesModal').style.display='none'" style="position: absolute; right: 16px; top: 16px; background: none; border: none; cursor: pointer; font-size: 1.5rem; color: var(--text-muted);">&times;</button>
        <h3 style="margin-top: 0; color: var(--brand-navy); margin-bottom: 16px;">Liked By</h3>
        <div id="likesListContainer" style="max-height: 300px; overflow-y: auto;">
            <!-- Rendered via JS -->
        </div>
    </div>
</div>

<script>
function showReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

async function showLikesModal(e) {
    e.preventDefault();
    document.getElementById('likesModal').style.display = 'flex';
    document.getElementById('likesListContainer').innerHTML = '<p style="text-align: center; color: var(--text-muted);">Loading...</p>';
    
    try {
        const response = await fetch('get_likes.php?post_id=<?= $postId ?>');
        const data = await response.json();
        
        let html = '';
        if (data.likes && data.likes.length > 0) {
            data.likes.forEach(user => {
                let avatar = user.profile_picture 
                    ? `<img src="<?= $_base ?>${user.profile_picture}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">`
                    : `<div style="width: 36px; height: 36px; border-radius: 50%; background: var(--surface-muted); display: flex; align-items: center; justify-content: center; font-weight: bold;">${user.full_name.charAt(0).toUpperCase()}</div>`;
                
                html += `
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        ${avatar}
                        <div style="font-weight: 600; color: var(--text-primary);">${user.full_name}</div>
                    </div>
                `;
            });
        } else {
            html = '<p style="text-align: center; color: var(--text-muted);">No likes yet.</p>';
        }
        document.getElementById('likesListContainer').innerHTML = html;
    } catch (err) {
        document.getElementById('likesListContainer').innerHTML = '<p style="text-align: center; color: var(--brand-coral);">Error loading likes.</p>';
    }
}

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
// Removed redundant like script, it's defined here.
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        <?php if (!$loggedIn): ?>
            window.location.href = '../login.php';
            return;
        <?php endif; ?>

        const postId = this.dataset.postId;
        const countSpan = document.getElementById(`likeCountDetail`);
        const textSpan = this.querySelector('.like-text');
        
        try {
            const response = await fetch('like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
                },
                body: JSON.stringify({ post_id: postId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.action === 'liked') {
                    this.classList.add('liked');
                    textSpan.textContent = 'Liked';
                } else {
                    this.classList.remove('liked');
                    textSpan.textContent = 'Like';
                }
                countSpan.textContent = data.likes;
            } else {
                alert(data.error || 'Something went wrong');
            }
        } catch (error) {
            console.error('Error liking post:', error);
        }
    });
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
