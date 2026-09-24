<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

$loggedIn = isLoggedIn();
$userId = $_SESSION['user_id'] ?? 0;

// Handle Post Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn) {
    verifyCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $topic = $_POST['topic'] ?? 'general';

    $validTopics = ['food', 'events', 'general', 'feedback'];
    if (!in_array($topic, $validTopics)) $topic = 'general';

    if ($title && $content) {
        $stmt = $pdo->prepare("INSERT INTO community_posts (author_id, topic, title, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $topic, $title, $content]);
        $_SESSION['flash_success'] = "Your post has been published!";
        header("Location: index.php");
        exit;
    }
}

// Fetch Posts
$topicFilter = $_GET['topic'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$query = "
    SELECT 
        p.*,
        u.full_name as author_name,
        u.role as author_role,
        (SELECT COUNT(*) FROM community_comments c WHERE c.post_id = p.post_id) as comment_count
    FROM community_posts p
    JOIN users u ON p.author_id = u.user_id
";

$params = [];
if ($topicFilter !== 'all') {
    $query .= " WHERE p.topic = ?";
    $params[] = $topicFilter;
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

<main style="max-width: 800px; margin: 0 auto; padding: 32px 16px;">
    <div style="text-align: center; margin-bottom: 32px;">
        <h1 style="font-size: 2.2rem; color: var(--brand-navy); margin-bottom: 8px;">Community Hub</h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem;">Discuss food, events, and hyper-local deals with your community.</p>
    </div>

    <!-- Create Post Form -->
    <?php if ($loggedIn): ?>
        <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-sm); margin-bottom: 32px;">
            <h2 style="font-size: 1.25rem; color: var(--brand-navy); margin-bottom: 16px;">Start a Discussion</h2>
            <form method="POST" action="index.php">
                <?= csrf_field() ?>
                
                <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                    <div style="flex: 1;">
                        <input type="text" name="title" placeholder="What do you want to talk about?" required class="form-input" style="width: 100%;">
                    </div>
                    <div>
                        <select name="topic" class="form-select" required>
                            <option value="general">General Chat</option>
                            <option value="food">Food Rescue</option>
                            <option value="events">Event Hype</option>
                            <option value="feedback">Feedback & Ideas</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <textarea name="content" rows="3" placeholder="Share your thoughts, ask questions, or recommend a hidden gem..." required class="form-input" style="width: 100%; resize: vertical;"></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="submit" class="primary-button" style="padding: 10px 24px;">Post to Community</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div style="background: #f8fafc; border: 1px dashed var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; text-align: center; margin-bottom: 32px;">
            <p style="color: var(--text-secondary); margin-bottom: 12px;">Log in to join the conversation and post in the community.</p>
            <a href="../login.php" class="primary-button" style="display: inline-block;">Log In</a>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div style="display: flex; gap: 12px; margin-bottom: 24px; overflow-x: auto; padding-bottom: 8px;">
        <a href="?topic=all" class="badge" style="background: <?= $topicFilter === 'all' ? 'var(--brand-navy)' : '#f1f5f9' ?>; color: <?= $topicFilter === 'all' ? 'white' : '#475569' ?>; font-size: 0.9rem; padding: 6px 16px; border-radius: 20px; text-decoration: none;">All Topics</a>
        <a href="?topic=food" class="badge" style="background: <?= $topicFilter === 'food' ? 'var(--brand-navy)' : '#f1f5f9' ?>; color: <?= $topicFilter === 'food' ? 'white' : '#475569' ?>; font-size: 0.9rem; padding: 6px 16px; border-radius: 20px; text-decoration: none;">Food Rescue</a>
        <a href="?topic=events" class="badge" style="background: <?= $topicFilter === 'events' ? 'var(--brand-navy)' : '#f1f5f9' ?>; color: <?= $topicFilter === 'events' ? 'white' : '#475569' ?>; font-size: 0.9rem; padding: 6px 16px; border-radius: 20px; text-decoration: none;">Event Hype</a>
        <a href="?topic=general" class="badge" style="background: <?= $topicFilter === 'general' ? 'var(--brand-navy)' : '#f1f5f9' ?>; color: <?= $topicFilter === 'general' ? 'white' : '#475569' ?>; font-size: 0.9rem; padding: 6px 16px; border-radius: 20px; text-decoration: none;">General Chat</a>
        <a href="?topic=feedback" class="badge" style="background: <?= $topicFilter === 'feedback' ? 'var(--brand-navy)' : '#f1f5f9' ?>; color: <?= $topicFilter === 'feedback' ? 'white' : '#475569' ?>; font-size: 0.9rem; padding: 6px 16px; border-radius: 20px; text-decoration: none;">Feedback</a>
    </div>

    <!-- Feed -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php if (empty($posts)): ?>
            <div style="text-align: center; padding: 48px; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
                <p style="color: var(--text-muted); font-size: 1.1rem;">No discussions found in this topic.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <a href="post.php?id=<?= $post['post_id'] ?>" style="display: block; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px; text-decoration: none; color: inherit; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='var(--brand-coral)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--border-subtle)'; this.style.transform='translateY(0)';">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <span class="badge" style="background: <?= getTopicColor($post['topic']) ?>; color: <?= getTopicTextColor($post['topic']) ?>; margin-bottom: 8px; display: inline-block;">
                                <?= e(ucfirst($post['topic'])) ?>
                            </span>
                            <h3 style="font-size: 1.25rem; color: var(--brand-navy); margin: 0 0 4px 0;"><?= e($post['title']) ?></h3>
                            <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                <strong><?= e($post['author_name']) ?></strong> 
                                <?php if ($post['author_role'] === 'seller' || $post['author_role'] === 'admin'): ?>
                                    <span class="badge" style="font-size: 0.65rem; padding: 2px 6px;"><?= ucfirst($post['author_role']) ?></span>
                                <?php endif; ?>
                                <span>&bull;</span>
                                <span><?= date('M j, Y g:i A', strtotime($post['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <p style="color: var(--text-secondary); line-height: 1.5; margin: 0 0 16px 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= nl2br(e($post['content'])) ?>
                    </p>
                    
                    <div style="display: flex; align-items: center; gap: 6px; color: var(--brand-navy); font-weight: 600; font-size: 0.9rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <?= $post['comment_count'] ?> <?= $post['comment_count'] === 1 ? 'Comment' : 'Comments' ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
