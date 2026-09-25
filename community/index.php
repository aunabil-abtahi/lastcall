<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

$loggedIn = isLoggedIn();
$userId = $_SESSION['user_id'] ?? 0;

// Handle Post Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn) {
    require_csrf();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $topic = $_POST['topic'] ?? 'general';

    $validTopics = ['food', 'events', 'general', 'feedback'];
    if (!in_array($topic, $validTopics)) $topic = 'general';

    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/images/community/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', basename($_FILES['image']['name']));
        $targetFile = $uploadDir . $fileName;
        
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imageUrl = 'assets/images/community/' . $fileName;
            }
        }
    }

    if ($title && $content) {
        $stmt = $pdo->prepare("INSERT INTO community_posts (author_id, topic, title, content, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $topic, $title, $content, $imageUrl]);
        $_SESSION['flash_success'] = "Your post has been published!";
        header("Location: index.php");
        exit;
    }
}

// Fetch Posts
$topicFilter = $_GET['topic'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$query = "
    SELECT 
        p.*,
        u.full_name as author_name,
        u.role as author_role,
        u.profile_picture as author_profile_picture,
        (SELECT COUNT(*) FROM community_comments c WHERE c.post_id = p.post_id) as comment_count,
        (SELECT COUNT(*) FROM community_likes l WHERE l.post_id = p.post_id) as like_count,
        (SELECT 1 FROM community_likes l2 WHERE l2.post_id = p.post_id AND l2.user_id = ?) as user_liked
    FROM community_posts p
    JOIN users u ON p.author_id = u.user_id
";

$params = [$userId];
$whereConditions = [];

if ($topicFilter !== 'all') {
    $whereConditions[] = "p.topic = ?";
    $params[] = $topicFilter;
}

if ($searchQuery !== '') {
    $whereConditions[] = "(p.title LIKE ? OR p.content LIKE ? OR u.full_name LIKE ?)";
    $wildcard = '%' . $searchQuery . '%';
    $params[] = $wildcard;
    $params[] = $wildcard;
    $params[] = $wildcard;
}

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(' AND ', $whereConditions);
}

$query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$pageTitle = "Community Hub | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<style>
    .community-header { text-align: center; margin-bottom: 40px; padding: 40px 20px; background: linear-gradient(135deg, var(--brand-navy-dark), var(--brand-navy)); border-radius: var(--radius-xl); color: white; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
    .community-header::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.05) 10%, transparent 20%), radial-gradient(circle, rgba(255,255,255,0.05) 10%, transparent 20%); background-size: 20px 20px; background-position: 0 0, 10px 10px; opacity: 0.3; }
    .community-title { font-size: 2.5rem; font-family: var(--font-serif); font-weight: 700; margin-bottom: 12px; position: relative; z-index: 2; }
    .community-subtitle { font-size: 1.15rem; color: var(--brand-navy-tint); opacity: 0.9; max-width: 600px; margin: 0 auto; position: relative; z-index: 2; }
    
    .create-post-card { background: var(--surface-card); border-radius: var(--radius-xl); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 40px; border: 1px solid var(--border-subtle); transition: var(--transition-smooth); }
    .create-post-card:focus-within { box-shadow: var(--shadow-lg); border-color: var(--border-medium); }
    
    .filter-tabs { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
    .filter-tabs::-webkit-scrollbar { display: none; }
    .filter-tab { background: var(--surface-card); border: 1px solid var(--border-subtle); color: var(--text-secondary); padding: 10px 24px; border-radius: 30px; font-weight: 600; font-size: 0.95rem; white-space: nowrap; transition: var(--transition-fast); cursor: pointer; box-shadow: var(--shadow-sm); }
    .filter-tab:hover { border-color: var(--brand-navy); color: var(--brand-navy); transform: translateY(-2px); }
    .filter-tab.active { background: var(--brand-navy); color: white; border-color: var(--brand-navy); box-shadow: 0 4px 12px rgba(30, 41, 84, 0.2); }
    
    /* Feed Styles */
    .post-card { background: var(--surface-card); border-radius: var(--radius-lg); margin-bottom: 24px; border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; }
    .post-header { padding: 20px 20px 12px 20px; display: flex; align-items: flex-start; justify-content: space-between; }
    .post-meta { display: flex; align-items: center; gap: 12px; }
    .post-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--brand-navy-tint); color: var(--brand-navy); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
    .post-author { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
    .post-date { font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 2px; }
    
    .post-content-wrap { padding: 0 20px 16px 20px; }
    .post-title { font-size: 1.25rem; color: var(--brand-navy); margin: 0 0 10px 0; font-weight: 700; line-height: 1.4; }
    .post-excerpt { color: var(--text-primary); line-height: 1.5; font-size: 1rem; margin-bottom: 0; }
    
    .post-image { width: 100%; max-height: 500px; object-fit: cover; display: block; border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle); }
    
    .post-stats { padding: 12px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; color: var(--text-muted); font-size: 0.9rem; }
    
    .post-actions { display: flex; padding: 4px 12px; }
    .action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; background: transparent; border: none; border-radius: 6px; color: var(--text-secondary); font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: var(--transition-fast); text-decoration: none; }
    .action-btn:hover { background: var(--surface-muted); color: var(--text-primary); }
    .action-btn.liked { color: var(--brand-coral); }
    .action-btn.liked svg { fill: var(--brand-coral); color: var(--brand-coral); }
    
    .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; cursor: pointer; }
    .file-input-wrapper input[type=file] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; }
    .file-input-btn { display: inline-flex; align-items: center; gap: 8px; color: var(--text-secondary); font-weight: 600; padding: 8px 16px; border-radius: 20px; background: var(--surface-muted); transition: var(--transition-fast); cursor: pointer; }
    .file-input-wrapper:hover .file-input-btn { background: #e2e8f0; color: var(--brand-navy); }
    
    .post-options { position: relative; }
    .post-options-btn { background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 8px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: var(--transition-fast); }
    .post-options-btn:hover { background: var(--surface-muted); color: var(--text-primary); }
    .post-dropdown { position: absolute; right: 0; top: 100%; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); min-width: 150px; z-index: 10; display: none; overflow: hidden; }
    .post-dropdown.show { display: block; }
    .post-dropdown-item { display: flex; align-items: center; gap: 8px; padding: 10px 16px; color: var(--text-primary); cursor: pointer; border: none; background: transparent; width: 100%; text-align: left; font-size: 0.95rem; }
    .post-dropdown-item:hover { background: var(--surface-muted); }
    .post-dropdown-item.danger { color: var(--brand-coral); }
</style>

<main style="max-width: 900px; margin: 0 auto; padding: 40px 16px; width: 100%; box-sizing: border-box;">
    <!-- Beautiful Header -->
    <div class="community-header">
        <h1 class="community-title">Community Feed</h1>
        <p class="community-subtitle">Discuss food rescues, event hype, and hyper-local deals with your neighbors.</p>
    </div>

    <!-- Actions Bar: Search (Moved below hero) -->
    <div style="margin-bottom: 24px;">
        <form method="GET" action="index.php" style="display: flex; gap: 8px;">
            <?php if ($topicFilter !== 'all'): ?>
                <input type="hidden" name="topic" value="<?= e($topicFilter) ?>">
            <?php endif; ?>
            <div style="position: relative; flex: 1;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search posts, topics, or authors..." style="width: 100%; box-sizing: border-box; padding: 14px 16px 14px 48px; border-radius: 30px; border: 1px solid var(--border-medium); font-size: 1rem; outline: none; transition: all 0.2s ease; background: var(--surface-card); box-shadow: var(--shadow-sm);" onfocus="this.style.borderColor='var(--brand-navy)'; this.style.boxShadow='0 0 0 3px var(--brand-navy-tint)';" onblur="this.style.borderColor='var(--border-medium)'; this.style.boxShadow='var(--shadow-sm)';">
            </div>
            <button type="submit" class="btn-primary-navy" style="padding: 0 28px; border-radius: 30px; font-weight: 600; box-shadow: var(--shadow-sm);">Search</button>
        </form>
    </div>

    <!-- Create Post Form (FB Style) -->
    <?php if ($loggedIn): ?>
        <div class="create-post-card" style="padding: 16px 20px;">
            <form method="POST" action="index.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                
                <div style="display: flex; gap: 12px; margin-bottom: 12px; align-items: flex-start;">
                    <div class="post-avatar" style="width: 40px; height: 40px; font-size: 1rem; overflow: hidden; margin-top: 4px;">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?= $_base . e($_SESSION['profile_picture']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= mb_strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <?php
                            $firstName = explode(' ', $_SESSION['full_name'] ?? 'User')[0];
                        ?>
                        <input type="text" name="title" placeholder="What's the topic, <?= e($firstName) ?>?" required style="width: 100%; box-sizing: border-box; font-size: 1.05rem; padding: 12px 20px; background: var(--surface-muted); border-radius: 30px; border: 1px solid transparent; outline: none; transition: all 0.2s; color: var(--text-primary); margin-bottom: 8px;" onfocus="this.style.background='white'; this.style.borderColor='var(--brand-navy-light)'; this.style.boxShadow='0 0 0 2px var(--brand-navy-tint)';" onblur="if(!this.value){ this.style.background='var(--surface-muted)'; this.style.borderColor='transparent'; this.style.boxShadow='none'; }">
                        
                        <textarea name="content" rows="2" placeholder="Write something more..." required style="width: 100%; box-sizing: border-box; font-size: 1rem; padding: 12px 16px; background: transparent; border: none; outline: none; resize: none; min-height: 60px;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                    </div>
                </div>
                
                <div style="border-top: 1px solid var(--border-subtle); padding-top: 12px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <div class="file-input-wrapper" style="margin: 0;">
                            <div class="file-input-btn" style="background: transparent; color: var(--text-secondary); padding: 8px 12px; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#45bd62" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                Photo/Video
                            </div>
                            <input type="file" name="image" accept="image/*" id="postImageInput">
                        </div>

                        <div style="position: relative; display: flex; align-items: center;">
                            <select name="topic" required style="font-size: 0.95rem; padding: 8px 32px 8px 16px; border-radius: 20px; border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-weight: 600; cursor: pointer; outline: none; appearance: none; transition: all 0.2s;" onfocus="this.style.borderColor='var(--brand-navy-light)'; this.style.boxShadow='0 0 0 2px var(--brand-navy-tint)';" onblur="this.style.borderColor='transparent'; this.style.boxShadow='none';">
                                <option value="general">💬 General</option>
                                <option value="food">🍔 Food Rescue</option>
                                <option value="events">🎟️ Event Hype</option>
                                <option value="feedback">💡 Feedback</option>
                            </select>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: 12px; pointer-events: none; color: var(--text-secondary);"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-navy" style="padding: 8px 20px; font-size: 0.95rem; border-radius: 6px; font-weight: 600;">
                        Post
                    </button>
                </div>
                <!-- Image Preview Area -->
                <div id="imagePreviewContainer" style="display: none; margin-top: 12px; border-radius: 8px; overflow: hidden; position: relative;">
                    <img id="imagePreview" src="" alt="Preview" style="width: 100%; max-height: 200px; object-fit: cover;">
                    <button type="button" id="clearImageBtn" style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer;">&times;</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div style="background: var(--brand-navy-tint); border: 2px dashed var(--border-medium); border-radius: var(--radius-xl); padding: 40px 24px; text-align: center; margin-bottom: 40px;">
            <div style="width: 64px; height: 64px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: var(--shadow-sm);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--brand-navy);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <h3 style="font-size: 1.5rem; color: var(--brand-navy); margin-bottom: 12px;">Join the Conversation</h3>
            <p style="color: var(--text-secondary); margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;">Log in to create posts, reply to threads, and like content from your community.</p>
            <a href="../login.php" class="btn-primary-navy" style="padding: 12px 32px; border-radius: 30px; display: inline-block;">Log In or Sign Up</a>
        </div>
    <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <?php 
                $qParam = $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : ''; 
            ?>
            <a href="?topic=all<?= $qParam ?>" class="filter-tab <?= $topicFilter === 'all' ? 'active' : '' ?>">All Topics</a>
            <a href="?topic=food<?= $qParam ?>" class="filter-tab <?= $topicFilter === 'food' ? 'active' : '' ?>">🍔 Food Rescue</a>
            <a href="?topic=events<?= $qParam ?>" class="filter-tab <?= $topicFilter === 'events' ? 'active' : '' ?>">🎟️ Event Hype</a>
            <a href="?topic=general<?= $qParam ?>" class="filter-tab <?= $topicFilter === 'general' ? 'active' : '' ?>">💬 General</a>
            <a href="?topic=feedback<?= $qParam ?>" class="filter-tab <?= $topicFilter === 'feedback' ? 'active' : '' ?>">💡 Feedback</a>
        </div>
    </div>

    <!-- Feed -->
    <div style="display: flex; flex-direction: column;">
        <?php if (empty($posts)): ?>
            <div style="text-align: center; padding: 64px 24px; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
                <div style="width: 80px; height: 80px; background: var(--surface-muted); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <h3 style="font-size: 1.5rem; color: var(--brand-navy); margin-bottom: 8px;">No posts yet</h3>
                <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 400px; margin: 0 auto;">Be the first to share something with the community.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-card">
                    <!-- Post Header (Author Info) -->
                    <div class="post-header">
                        <div class="post-meta">
                            <div class="post-avatar" style="overflow: hidden;">
                                <?php if (!empty($post['author_profile_picture'])): ?>
                                    <img src="<?= $_base . e($post['author_profile_picture']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <?= mb_strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="post-author" style="display: flex; align-items: center; gap: 8px;">
                                    <?= e($post['author_name']) ?>
                                    <?php if ($post['author_role'] === 'seller'): ?>
                                        <span style="background: var(--brand-emerald-tint); color: var(--brand-emerald-dark); font-size: 0.65rem; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; font-weight: 800;">Vendor</span>
                                    <?php elseif ($post['author_role'] === 'admin'): ?>
                                        <span style="background: var(--brand-coral-tint); color: var(--brand-coral-hover); font-size: 0.65rem; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; font-weight: 800;">Admin</span>
                                    <?php endif; ?>
                                </div>
                                <div class="post-date">
                                    <?= date('F j \a\t g:i a', strtotime($post['created_at'])) ?>
                                    <?php if ($post['is_edited'] ?? false): ?>
                                        <a href="edit_history.php?id=<?= $post['post_id'] ?>" style="color: inherit; text-decoration: underline; margin-left: 4px;">(Edited)</a>
                                    <?php endif; ?>
                                    · 
                                    <span style="color: <?= getTopicTextColor($post['topic']) ?>; font-weight: 600;">
                                        <?= e(ucfirst($post['topic'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if ($loggedIn): ?>
                        <div class="post-options">
                            <button class="post-options-btn" onclick="toggleDropdown(<?= $post['post_id'] ?>)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
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
                    
                    <!-- Post Text -->
                    <div class="post-content-wrap">
                        <h3 class="post-title"><?= e($post['title']) ?></h3>
                        <p class="post-excerpt"><?= nl2br(e($post['content'])) ?></p>
                    </div>

                    <!-- Post Image -->
                    <?php if (!empty($post['image_url'])): ?>
                        <img src="<?= $_base . e($post['image_url']) ?>" alt="Post attached image" class="post-image">
                    <?php endif; ?>
                    
                    <!-- Post Stats (Likes & Comments counts) -->
                    <div class="post-stats">
                        <div>
                            <a href="#" onclick="showLikesModal(event, <?= $post['post_id'] ?>)" style="color: inherit; text-decoration: none;">
                                <span id="likeCount_<?= $post['post_id'] ?>"><?= $post['like_count'] ?></span> <span id="likeText_<?= $post['post_id'] ?>"><?= $post['like_count'] === 1 ? 'Like' : 'Likes' ?></span>
                            </a>
                        </div>
                        <div>
                            <a href="post.php?id=<?= $post['post_id'] ?>" style="color: inherit; text-decoration: none;">
                                <?= $post['comment_count'] ?> <?= $post['comment_count'] === 1 ? 'Comment' : 'Comments' ?>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Action Bar -->
                    <div class="post-actions">
                        <button class="action-btn like-btn <?= $post['user_liked'] ? 'liked' : '' ?>" data-post-id="<?= $post['post_id'] ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                            </svg>
                            <span class="like-text"><?= $post['user_liked'] ? 'Liked' : 'Like' ?></span>
                        </button>
                        
                        <a href="post.php?id=<?= $post['post_id'] ?>" class="action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                            </svg>
                            Comment
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Likes Modal -->
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
// Image Upload Preview
const imageInput = document.getElementById('postImageInput');
const previewContainer = document.getElementById('imagePreviewContainer');
const previewImg = document.getElementById('imagePreview');
const clearBtn = document.getElementById('clearImageBtn');

if (imageInput) {
    imageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    clearBtn.addEventListener('click', function() {
        imageInput.value = '';
        previewContainer.style.display = 'none';
        previewImg.src = '';
    });
}

// AJAX Like Functionality
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        <?php if (!$loggedIn): ?>
            window.location.href = '../login.php';
            return;
        <?php endif; ?>

        const postId = this.dataset.postId;
        const countSpan = document.getElementById(`likeCount_${postId}`);
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
                
                // Update text content with pluralization handled slightly simply
                // since we want to keep "X Likes" format, we just update the number
                countSpan.textContent = data.likes;
                const likesWordSpan = document.getElementById(`likeText_${postId}`);
                if (likesWordSpan) {
                    likesWordSpan.textContent = data.likes === 1 ? 'Like' : 'Likes';
                }
            } else {
                alert(data.error || 'Something went wrong');
            }
        } catch (error) {
            console.error('Error liking post:', error);
        }
    });
});
</script>

<script>
async function showLikesModal(e, postId) {
    e.preventDefault();
    document.getElementById('likesModal').style.display = 'flex';
    document.getElementById('likesListContainer').innerHTML = '<p style="text-align: center; color: var(--text-muted);">Loading...</p>';
    
    try {
        const response = await fetch(`get_likes.php?post_id=${postId}`);
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
    
    // Close other dropdowns
    document.querySelectorAll('.post-dropdown').forEach(el => {
        if (el.id !== 'dropdown_' + postId) el.classList.remove('show');
    });

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
            window.location.reload();
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
