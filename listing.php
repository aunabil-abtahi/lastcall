<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

runMarketplaceMaintenance($pdo);

$listingId = (int) ($_GET["id"] ?? 0);

$listingQuery = $pdo->prepare("
    SELECT
        l.listing_id,
        l.seller_id,
        l.listing_type,
        l.title,
        l.description,
        l.original_price,
        l.pickup_or_event_deadline,
        loc.city,
        loc.area,
        f.food_category,
        f.quantity_available,
        f.pickup_start_at,
        t.ticket_id,
        t.current_owner_id AS ticket_owner_id,
        COALESCE((
            SELECT dr.discount_percent
            FROM discount_rules dr
            WHERE dr.listing_id = l.listing_id
              AND dr.threshold_minutes >= TIMESTAMPDIFF(
                  MINUTE, NOW(), l.pickup_or_event_deadline
              )
            ORDER BY dr.threshold_minutes ASC
            LIMIT 1
        ), 0) AS discount_percent,
        TIMESTAMPDIFF(MINUTE, NOW(), l.pickup_or_event_deadline) AS minutes_left,
        sp.business_name AS seller_business_name,
        sp.seller_type,
        sp.verification_status AS seller_verification_status
    FROM listings l
    JOIN locations loc ON loc.location_id = l.location_id
    LEFT JOIN food_listing_details f ON f.listing_id = l.listing_id
    LEFT JOIN ticket_listings tl ON tl.listing_id = l.listing_id
    LEFT JOIN tickets t ON t.ticket_id = tl.ticket_id
    LEFT JOIN seller_profiles sp ON sp.user_id = l.seller_id
    WHERE l.listing_id = ?
      AND l.listing_status = 'active'
      AND l.pickup_or_event_deadline > NOW()
      AND (
          (l.listing_type = 'food' AND f.quantity_available > 0)
          OR (
              l.listing_type = 'ticket'
              AND t.verification_status = 'verified'
              AND t.availability_status = 'available'
          )
      )
    LIMIT 1
");
$listingQuery->execute([$listingId]);
$listing = $listingQuery->fetch(PDO::FETCH_ASSOC);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$sellerStats = ["average_rating" => 0.0, "review_count" => 0];
$sellerReviews = [];
$isFollowing = false;

if ($listing) {
    $sellerId = (int) $listing["seller_id"];

    // Fetch seller reputation analytics from view
    $statsStmt = $pdo->prepare("
        SELECT average_rating, review_count
        FROM view_seller_analytics
        WHERE seller_id = ?
        LIMIT 1
    ");
    $statsStmt->execute([$sellerId]);
    $fetchedStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($fetchedStats) {
        $sellerStats = $fetchedStats;
    }

    // Fetch recent reviews for this seller from view
    $reviewsStmt = $pdo->prepare("
        SELECT review_id, buyer_name, rating, review_text, created_at, item_title
        FROM view_listing_reviews
        WHERE seller_id = ?
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $reviewsStmt->execute([$sellerId]);
    $sellerReviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if current user is following this seller
    if (isLoggedIn()) {
        $followCheck = $pdo->prepare("
            SELECT 1 FROM followed_sellers 
            WHERE follower_id = ? AND seller_id = ?
            LIMIT 1
        ");
        $followCheck->execute([(int) $_SESSION["user_id"], $sellerId]);
        $isFollowing = (bool) $followCheck->fetchColumn();
    }

    $pageTitle = e($listing["title"]) . " | LastCall";
} else {
    http_response_code(404);
    $pageTitle = "Listing Not Found | LastCall";
}

require_once __DIR__ . "/includes/header.php";
?>

<main class="container">
    <?php if (!$listing): ?>
        <div class="review-empty-state" style="margin: 60px auto; max-width: 600px;">
            <div class="empty-icon">🔍</div>
            <h2 style="color: var(--brand-navy); margin-bottom: 8px;">Listing Not Available</h2>
            <p>This listing may have expired, sold out, or been removed by the seller.</p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn-demo-action studio-btn" style="display: inline-block;">
                    &larr; Return to Marketplace
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php
            $discount = (float) $listing["discount_percent"];
            $basePrice = (float) $listing["original_price"];
            $currentPrice = $basePrice * (1 - ($discount / 100));
            $isFood = $listing["listing_type"] === "food";
            $minutesLeft = (int) $listing["minutes_left"];
            $timeLeft = $minutesLeft <= 60
                ? $minutesLeft . " minutes left"
                : ceil($minutesLeft / 60) . " hours left";
            $sellerName = $listing["seller_business_name"] ?: "Verified Seller";
            $isOwnListing = isLoggedIn() && (
                ($isFood && (int) $_SESSION["user_id"] === (int) $listing["seller_id"]) ||
                (!$isFood && (int) $_SESSION["user_id"] === (int) $listing["ticket_owner_id"])
            );
        ?>

        <div class="detail-layout">
            <!-- Left Column: Main Listing Details -->
            <section class="detail-card">
                <span class="badge <?= $isFood ? "" : "ticket" ?>">
                    <?= $isFood ? "🍽️ Food Rescue" : "🎟️ Event Ticket" ?>
                </span>

                <h1><?= e($listing["title"]) ?></h1>
                <p class="detail-description"><?= nl2br(e($listing["description"] ?? "No description provided for this listing.")) ?></p>

                <div class="detail-meta-grid">
                    <div class="detail-meta-item">
                        <span class="meta-icon">📍</span>
                        <div>
                            <span class="meta-label">Location</span>
                            <?= e($listing["area"]) ?>, <?= e($listing["city"]) ?>
                        </div>
                    </div>

                    <div class="detail-meta-item">
                        <span class="meta-icon">⏰</span>
                        <div>
                            <span class="meta-label">Pickup / Event Deadline</span>
                            <span style="<?= $minutesLeft <= 60 ? 'color: var(--brand-coral); font-weight: 800;' : '' ?>">
                                <?= e($timeLeft) ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($isFood): ?>
                        <div class="detail-meta-item">
                            <span class="meta-icon">🍱</span>
                            <div>
                                <span class="meta-label">Category</span>
                                <?= e(ucfirst($listing["food_category"] ?? "Meal")) ?>
                            </div>
                        </div>

                        <div class="detail-meta-item">
                            <span class="meta-icon">📦</span>
                            <div>
                                <span class="meta-label">Quantity Available</span>
                                <?= (int) $listing["quantity_available"] ?> portions
                            </div>
                        </div>

                        <?php if (!empty($listing["pickup_start_at"])): ?>
                            <div class="detail-meta-item">
                                <span class="meta-icon">🕒</span>
                                <div>
                                    <span class="meta-label">Pickup Window Starts</span>
                                    <?= date("d M, h:i A", strtotime($listing["pickup_start_at"])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Price & Discount Box -->
                <div class="detail-price-box">
                    <div class="detail-price-left">
                        <?php if ($discount > 0): ?>
                            <span class="detail-old-price">৳<?= number_format($basePrice, 2) ?></span>
                            <span class="detail-discount-pill">
                                ⚡ <?= number_format($discount, 0) ?>% OFF
                            </span>
                        <?php endif; ?>
                        <div class="detail-current-price">
                            ৳<?= number_format($currentPrice, 2) ?>
                        </div>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div style="font-size: 13px; color: #92400e; font-weight: 700;">
                            ✨ You save ৳<?= number_format($basePrice - $currentPrice, 2) ?>!
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action CTA -->
                <div class="detail-actions">
                    <?php if ($isFood && isLoggedIn() && !$isOwnListing): ?>
                        <a class="btn-reserve-cta" href="reserve_food.php?listing_id=<?= (int) $listing["listing_id"] ?>">
                            <span>🛒 Reserve Food for 5 Minutes</span>
                        </a>
                    <?php elseif (!$isFood && isLoggedIn() && !$isOwnListing): ?>
                        <a class="btn-reserve-cta" href="reserve_ticket.php?listing_id=<?= (int) $listing["listing_id"] ?>">
                            <span>🎟️ Reserve Ticket for 5 Minutes</span>
                        </a>
                    <?php elseif ($isOwnListing): ?>
                        <div class="alert alert-info" style="margin: 0; text-align: center;">
                            ℹ️ You are the owner of this listing. You cannot reserve your own listing.
                        </div>
                    <?php else: ?>
                        <a class="btn-reserve-cta" href="login.php">
                            <span>🔐 Log In to Reserve (<?= $isFood ? 'Food' : 'Ticket' ?>)</span>
                        </a>
                    <?php endif; ?>

                    <?php if (isLoggedIn() && !$isOwnListing): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; font-size: 13px;">
                            <a href="report.php?listing_id=<?= (int) $listing["listing_id"] ?>" style="color: var(--text-muted); text-decoration: none;">
                                🚩 Report this listing
                            </a>
                            <a href="index.php" style="color: var(--brand-navy); font-weight: 600; text-decoration: none;">
                                &larr; Back to Marketplace
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Right Column: Seller Reputation & Info Card -->
            <aside class="seller-reputation-card">
                <div class="seller-reputation-header">
                    <div class="seller-avatar-circle">
                        <?= mb_strtoupper(mb_substr($sellerName, 0, 1)) ?>
                    </div>
                    <div class="seller-title-wrap">
                        <h3><?= e($sellerName) ?></h3>
                        <div class="seller-type-tag">
                            <?php
                                $typeMap = [
                                    "food_business" => "Food Vendor",
                                    "individual_ticket_seller" => "Ticket Reseller",
                                    "event_organizer" => "Event Organizer"
                                ];
                                echo e($typeMap[$listing["seller_type"] ?? ""] ?? "Verified Merchant");
                            ?>
                        </div>
                    </div>
                </div>

                <?php if (($listing["seller_verification_status"] ?? "") === "approved"): ?>
                    <div class="seller-verified-badge">
                        <span>🛡️</span> Verified Marketplace Partner
                    </div>
                <?php endif; ?>

                <div class="seller-reputation-stats">
                    <div class="seller-rating-num">
                        <?= (float) $sellerStats["average_rating"] > 0 ? number_format((float) $sellerStats["average_rating"], 1) : "—" ?>
                        <span class="star-icon">★</span>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 13px; color: var(--text-primary);">
                            Seller Reputation
                        </div>
                        <div class="seller-reviews-count">
                            <?= (int) $sellerStats["review_count"] ?> verified customer <?= (int) $sellerStats["review_count"] === 1 ? 'review' : 'reviews' ?>
                        </div>
                    </div>
                </div>

                <?php if (isLoggedIn() && !$isOwnListing): ?>
                    <form method="post" action="follow_seller.php" class="seller-follow-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="seller_id" value="<?= (int) $listing["seller_id"] ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing["listing_id"] ?>">
                        <button type="submit" class="btn-follow-toggle <?= $isFollowing ? 'is-following' : '' ?>">
                            <?= $isFollowing ? '✓ Following Vendor' : '+ Follow Vendor' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </aside>
        </div>

        <!-- Customer Reviews Section -->
        <section class="reviews-section-card">
            <div class="reviews-section-header">
                <h2>
                    <span>⭐</span> Verified Customer Reviews
                    <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">
                        (<?= count($sellerReviews) ?>)
                    </span>
                </h2>
                <?php if ((float) $sellerStats["average_rating"] > 0): ?>
                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: var(--text-primary);">
                        <span style="color: #f59e0b; font-size: 18px;">★</span>
                        <span><?= number_format((float) $sellerStats["average_rating"], 1) ?> out of 5.0</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($sellerReviews) > 0): ?>
                <div class="reviews-cards-grid">
                    <?php foreach ($sellerReviews as $review): ?>
                        <div class="review-card-item">
                            <div>
                                <div class="review-card-top">
                                    <div class="review-stars-gold">
                                        <?php
                                            $rating = (int) $review["rating"];
                                            echo str_repeat("★", $rating) . str_repeat("☆", 5 - $rating);
                                        ?>
                                    </div>
                                    <span class="review-date-badge">
                                        <?= date("d M Y", strtotime($review["created_at"])) ?>
                                    </span>
                                </div>
                                <div class="review-quote" style="margin-top: 10px;">
                                    "<?= e($review["review_text"] ?: "Great experience and rescued food quality!") ?>"
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
                    <div class="empty-icon">💬</div>
                    <p style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">No customer reviews yet</p>
                    <p>Be the first to leave a review after your order is completed!</p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
