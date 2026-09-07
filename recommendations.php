<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

requireLogin();
runMarketplaceMaintenance($pdo);

$uid = (int) $_SESSION["user_id"];

$sql = "
    SELECT 
        l.listing_id,
        l.title,
        l.description,
        l.listing_type,
        l.original_price,
        loc.city,
        loc.area,
        f.quantity_available,
        TIMESTAMPDIFF(MINUTE, NOW(), l.pickup_or_event_deadline) AS minutes_left,
        CASE 
            WHEN fs.follower_id IS NOT NULL THEN 4
            WHEN EXISTS (
                SELECT 1 FROM user_preferences up 
                WHERE up.user_id = ? 
                  AND (up.preferred_listing_type = l.listing_type OR up.preferred_city = loc.city OR up.preferred_area = loc.area)
            ) THEN 3
            WHEN EXISTS (
                SELECT 1 FROM orders prev_o
                JOIN order_items prev_oi ON prev_oi.order_id = prev_o.order_id
                JOIN listings prev_l ON prev_l.listing_id = prev_oi.listing_id
                WHERE prev_o.buyer_id = ? 
                  AND prev_o.order_status = 'completed'
                  AND (prev_l.seller_id = l.seller_id OR prev_l.listing_type = l.listing_type)
            ) THEN 2
            WHEN user_loc.city = loc.city AND user_loc.area = loc.area THEN 1
            ELSE 0 
        END AS relevance,
        COALESCE((
            SELECT dr.discount_percent
            FROM discount_rules dr
            WHERE dr.listing_id = l.listing_id
              AND dr.threshold_minutes >= TIMESTAMPDIFF(MINUTE, NOW(), l.pickup_or_event_deadline)
            ORDER BY dr.threshold_minutes ASC
            LIMIT 1
        ), 0) AS discount_percent
    FROM listings l
    JOIN locations loc ON loc.location_id = l.location_id
    JOIN users u ON u.user_id = ?
    LEFT JOIN locations user_loc ON user_loc.location_id = u.location_id
    LEFT JOIN followed_sellers fs ON fs.seller_id = l.seller_id AND fs.follower_id = ?
    LEFT JOIN food_listing_details f ON f.listing_id = l.listing_id
    LEFT JOIN ticket_listings tl ON tl.listing_id = l.listing_id
    LEFT JOIN tickets t ON t.ticket_id = tl.ticket_id
    WHERE l.listing_status = 'active'
      AND l.pickup_or_event_deadline > NOW()
      AND (
          (l.listing_type = 'food' AND f.quantity_available > 0)
          OR (l.listing_type = 'ticket' AND t.verification_status = 'verified' AND t.availability_status = 'available')
      )
    ORDER BY relevance DESC, l.pickup_or_event_deadline ASC
    LIMIT 12
";

$q = $pdo->prepare($sql);
$q->execute([$uid, $uid, $uid, $uid]);
$listings = $q->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Recommendations | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="container">
    <div class="section-heading">
        <h2>Recommended for You</h2>
        <p>Personalized deals prioritized by your location, preferences, purchase history, and followed sellers.</p>
    </div>

    <?php if (count($listings) > 0): ?>
        <div class="listing-grid">
            <?php foreach ($listings as $l): ?>
                <?php
                    $discount = (float) $l["discount_percent"];
                    $originalPrice = (float) $l["original_price"];
                    $currentPrice = $originalPrice * (1 - ($discount / 100));
                    $isFood = $l["listing_type"] === "food";
                    $timeLeft = $l["minutes_left"] <= 60
                        ? $l["minutes_left"] . " minutes left"
                        : ceil($l["minutes_left"] / 60) . " hours left";
                ?>
                <article class="card">
                    <span class="badge <?= $isFood ? "" : "ticket" ?>">
                        <?= $isFood ? "Food Rescue" : "Event Ticket" ?>
                    </span>

                    <h3><?= e($l["title"]) ?></h3>

                    <p class="description">
                        <?= e($l["description"] ?? "No description available.") ?>
                    </p>

                    <p class="location">
                        📍 <?= e($l["area"]) ?>, <?= e($l["city"]) ?>
                    </p>

                    <p class="meta">⏰ <?= e($timeLeft) ?></p>

                    <?php if ($isFood && isset($l["quantity_available"])): ?>
                        <p class="meta">🍽 <?= (int) $l["quantity_available"] ?> available</p>
                    <?php endif; ?>

                    <div class="price-row">
                        <?php if ($discount > 0): ?>
                            <div class="old-price">৳<?= number_format($originalPrice, 2) ?></div>
                        <?php endif; ?>
                        <div class="current-price">৳<?= number_format($currentPrice, 2) ?></div>
                        <?php if ($discount > 0): ?>
                            <div class="discount"><?= number_format($discount, 0) ?>% discount</div>
                        <?php endif; ?>
                    </div>

                    <a class="card-link" href="listing.php?id=<?= (int) $l["listing_id"] ?>">View Deal</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-message">No active recommendations available right now. Check back soon!</p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
