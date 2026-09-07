<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

requireLogin();
runMarketplaceMaintenance($pdo);

$uid = (int) $_SESSION["user_id"];

// Query all sellers followed by the current user
$stmt = $pdo->prepare("
    SELECT 
        u.user_id AS seller_id,
        u.full_name AS seller_name,
        sp.business_name,
        sp.seller_type,
        loc.city,
        loc.area,
        fs.followed_at,
        (
            SELECT COUNT(*) 
            FROM listings l 
            WHERE l.seller_id = u.user_id 
              AND l.listing_status = 'active' 
              AND l.pickup_or_event_deadline > NOW()
        ) AS active_deal_count,
        (
            SELECT ROUND(AVG(r.rating), 1)
            FROM reviews r
            WHERE r.seller_id = u.user_id
        ) AS avg_rating,
        (
            SELECT COUNT(*)
            FROM reviews r
            WHERE r.seller_id = u.user_id
        ) AS review_count
    FROM followed_sellers fs
    JOIN users u ON u.user_id = fs.seller_id
    LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
    LEFT JOIN locations loc ON loc.location_id = u.location_id
    WHERE fs.follower_id = ?
    ORDER BY fs.followed_at DESC
");
$stmt->execute([$uid]);
$followed = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Followed Vendors | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="container">
    <div class="section-heading">
        <h2>Followed Vendors</h2>
        <p>Stay up to date with your favorite bakeries, restaurants, and event organizers. Deals from these vendors get boosted in your personalized recommendations.</p>
    </div>

    <?php if (count($followed) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 20px;">
            <?php foreach ($followed as $s): ?>
                <?php
                    $displayName = !empty($s["business_name"]) ? $s["business_name"] : $s["seller_name"];
                    $typeFormatted = match ($s["seller_type"] ?? "") {
                        "food_business" => "Food Business",
                        "event_organizer" => "Event Organizer",
                        "individual_ticket_seller" => "Ticket Reseller",
                        default => "Verified Seller"
                    };
                    $rating = (float) ($s["avg_rating"] ?? 0);
                    $reviews = (int) ($s["review_count"] ?? 0);
                    $activeDeals = (int) $s["active_deal_count"];
                ?>
                <article class="seller-card" style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="badge" style="display: inline-block;">
                                <?= e($typeFormatted) ?>
                            </span>
                            <span style="font-size: 12px; color: #8b93a1;">
                                Following since <?= date("M Y", strtotime($s["followed_at"])) ?>
                            </span>
                        </div>

                        <h3 style="color: #172033; font-size: 20px; margin-bottom: 4px;">
                            <?= e($displayName) ?>
                        </h3>

                        <?php if (!empty($s["city"])): ?>
                            <p style="color: #687080; font-size: 14px; margin-bottom: 8px;">
                                📍 <?= e($s["area"] ? $s["area"] . ", " . $s["city"] : $s["city"]) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Ratings summary -->
                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 14px;">
                            <?php if ($reviews > 0): ?>
                                <span style="color: #f5a900; font-weight: bold;">★ <?= number_format($rating, 1) ?></span>
                                <span style="color: #8b93a1;">(<?= $reviews ?> review<?= $reviews > 1 ? 's' : '' ?>)</span>
                            <?php else: ?>
                                <span style="color: #8b93a1; font-size: 13px;">No reviews yet</span>
                            <?php endif; ?>
                        </div>

                        <!-- Active deals pill -->
                        <div style="margin-bottom: 16px;">
                            <?php if ($activeDeals > 0): ?>
                                <span style="background: #dff5e5; color: #167234; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                    🔥 <?= $activeDeals ?> active deal<?= $activeDeals > 1 ? 's' : '' ?> now
                                </span>
                            <?php else: ?>
                                <span style="background: #f1f3f7; color: #8b93a1; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                                    No active deals currently
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 14px; border-top: 1px solid #edf0f4; margin-top: auto;">
                        <a href="index.php?q=<?= urlencode($displayName) ?>" class="card-link" style="text-decoration: none; font-size: 13px; font-weight: bold;">
                            View Deals &rarr;
                        </a>

                        <form method="post" action="follow_seller.php" style="margin: 0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="seller_id" value="<?= (int) $s['seller_id'] ?>">
                            <input type="hidden" name="return_to" value="followed_sellers.php">
                            <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Are you sure you want to unfollow <?= addslashes($displayName) ?>?');">
                                Unfollow
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: 48px 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div style="font-size: 48px; margin-bottom: 14px;">🏪</div>
            <h3 style="margin-bottom: 8px;">You Haven't Followed Any Vendors Yet</h3>
            <p style="color: #64748b; max-width: 500px; margin: 0 auto 20px;">
                Following sellers boosts their surplus deals and tickets in your personalized recommendations feed. You can follow any vendor directly from their listing pages!
            </p>
            <a class="primary-button" href="index.php" style="display: inline-block; padding: 10px 20px; text-decoration: none;">
                Explore Marketplace
            </a>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
