<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../login.php");
    exit;
}

$sellerId = (int) $_SESSION["user_id"];

// Fetch seller reputation analytics from view
$statsStmt = $pdo->prepare("
    SELECT average_rating, review_count, business_name, seller_type
    FROM view_seller_analytics
    WHERE seller_id = ?
    LIMIT 1
");
$statsStmt->execute([$sellerId]);
$sellerStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    "average_rating" => 0.0,
    "review_count" => 0,
    "business_name" => "Seller",
    "seller_type" => "food_business"
];

$totalReviews = (int) $sellerStats["review_count"];
$averageRating = (float) $sellerStats["average_rating"];

// Calculate star breakdown (5★ down to 1★)
$distStmt = $pdo->prepare("
    SELECT rating, COUNT(*) AS count
    FROM reviews
    WHERE seller_id = ?
    GROUP BY rating
");
$distStmt->execute([$sellerId]);
$distRows = $distStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

$starCounts = [
    5 => (int) ($distRows[5] ?? 0),
    4 => (int) ($distRows[4] ?? 0),
    3 => (int) ($distRows[3] ?? 0),
    2 => (int) ($distRows[2] ?? 0),
    1 => (int) ($distRows[1] ?? 0),
];

// Fetch list of reviews for this seller
$reviewsStmt = $pdo->prepare("
    SELECT review_id, order_id, rating, review_text, created_at, buyer_name, item_title
    FROM view_listing_reviews
    WHERE seller_id = ?
    ORDER BY created_at DESC
");
$reviewsStmt->execute([$sellerId]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Customer Reviews & Reputation | LastCall Seller";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2>⭐ Customer Reviews &amp; Reputation</h2>
                <p>Track customer satisfaction and verified feedback on your surplus food and tickets.</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn-demo-action studio-btn">
                    &larr; Seller Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Rating Overview Hero Card -->
    <section class="rating-overview-hero">
        <div class="rating-score-box">
            <div class="rating-big-number">
                <?= $averageRating > 0 ? number_format($averageRating, 1) : "—" ?>
            </div>
            <div class="stars-row">
                <?php
                    $roundStars = (int) round($averageRating);
                    echo str_repeat("★", $roundStars) . str_repeat("☆", 5 - $roundStars);
                ?>
            </div>
            <div class="rating-total-reviews">
                Based on <?= $totalReviews ?> <?= $totalReviews === 1 ? "review" : "reviews" ?>
            </div>
        </div>

        <div class="rating-bars-list">
            <?php for ($star = 5; $star >= 1; $star--): ?>
                <?php
                    $count = $starCounts[$star];
                    $percent = $totalReviews > 0 ? round(($count / $totalReviews) * 100) : 0;
                ?>
                <div class="rating-bar-row">
                    <span class="rating-bar-label"><?= $star ?> ★</span>
                    <div class="rating-bar-track">
                        <div class="rating-bar-fill" style="width: <?= $percent ?>%;"></div>
                    </div>
                    <span class="rating-bar-count"><?= $count ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- Review Cards Feed -->
    <div class="section-heading" style="margin-top: 24px; margin-bottom: 18px;">
        <h3 style="font-size: 19px; font-weight: 800; color: var(--brand-navy);">
            Recent Feedback Feed (<?= count($reviews) ?>)
        </h3>
    </div>

    <?php if (count($reviews) > 0): ?>
        <div class="reviews-cards-grid">
            <?php foreach ($reviews as $review): ?>
                <div class="review-card-item">
                    <div>
                        <div class="review-card-top">
                            <div class="review-stars-gold">
                                <?php
                                    $r = (int) $review["rating"];
                                    echo str_repeat("★", $r) . str_repeat("☆", 5 - $r);
                                ?>
                            </div>
                            <span class="review-date-badge">
                                <?= date("d M Y", strtotime($review["created_at"])) ?>
                            </span>
                        </div>
                        <div class="review-quote" style="margin-top: 10px;">
                            "<?= e($review["review_text"] ?: "Customer gave a " . (int)$review["rating"] . "-star rating.") ?>"
                        </div>
                    </div>
                    <div class="review-author-meta">
                        <span class="review-buyer-name">
                            👤 <?= e($review["buyer_name"]) ?>
                        </span>
                        <?php if (!empty($review["item_title"])): ?>
                            <span class="review-item-name" title="<?= e($review["item_title"]) ?>">
                                📦 <?= e($review["item_title"]) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="review-empty-state">
            <div class="empty-icon">🌟</div>
            <h3 style="color: var(--brand-navy); margin-bottom: 6px;">No Customer Reviews Yet</h3>
            <p>Customer reviews will automatically appear here once buyers complete their orders and share feedback.</p>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
