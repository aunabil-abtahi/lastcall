<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/maintenance.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../index.php");
    exit;
}

$sellerId = $_SESSION["user_id"];
runMarketplaceMaintenance($pdo);
$message = $_SESSION["seller_message"] ?? "";
unset($_SESSION["seller_message"]);

$sellerTypeQuery = $pdo->prepare("
    SELECT seller_type
    FROM seller_profiles
    WHERE user_id = ?
    LIMIT 1
");

$sellerTypeQuery->execute([$sellerId]);
$sellerType = $sellerTypeQuery->fetchColumn();

$listingsQuery = $pdo->prepare("
    SELECT
        l.listing_id,
        l.title,
        l.listing_type,
        l.original_price,
        l.pickup_or_event_deadline,
        l.listing_status,
        f.quantity_available,
        loc.city,
        loc.area
    FROM listings l
    LEFT JOIN food_listing_details f ON l.listing_id = f.listing_id
    JOIN locations loc ON l.location_id = loc.location_id
    WHERE l.seller_id = ?
    ORDER BY l.created_at DESC
");

$listingsQuery->execute([$sellerId]);
$listings = $listingsQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch seller analytics KPIs
$analyticsQuery = $pdo->prepare("SELECT * FROM view_seller_analytics WHERE seller_id = ? LIMIT 1");
$analyticsQuery->execute([$sellerId]);
$analytics = $analyticsQuery->fetch(PDO::FETCH_ASSOC) ?: [
    "total_revenue" => 0.00,
    "completed_orders" => 0,
    "total_listings" => 0,
    "active_listings" => 0,
    "total_items_sold" => 0,
    "average_rating" => 0.0,
    "review_count" => 0
];

$pageTitle = "Seller Dashboard | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>Seller Dashboard</h2>
        <p>Welcome, <?= e(currentUserName()) ?>. Manage your listings and track performance.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="kpi-card" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.82rem; color: var(--text-muted, #64748b); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">💰 Total Revenue</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-color, #0f172a);">৳<?= number_format((float)$analytics['total_revenue'], 2) ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted, #64748b); margin-top: 0.25rem;"><?= (int)$analytics['completed_orders'] ?> completed orders</div>
        </div>

        <div class="kpi-card" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.82rem; color: var(--text-muted, #64748b); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">⚡ Active Deals</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary, #f97316);"><?= (int)$analytics['active_listings'] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted, #64748b); margin-top: 0.25rem;">of <?= (int)$analytics['total_listings'] ?> total listings</div>
        </div>

        <div class="kpi-card" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.82rem; color: var(--text-muted, #64748b); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">📦 Items Rescued</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-color, #0f172a);"><?= (int)$analytics['total_items_sold'] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted, #64748b); margin-top: 0.25rem;">units saved from waste</div>
        </div>

        <div class="kpi-card" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <div style="font-size: 0.82rem; color: var(--text-muted, #64748b); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">⭐ Reputation</div>
                <a href="reviews.php" style="font-size: 0.75rem; color: var(--primary, #f97316); font-weight: 600; text-decoration: none;">View Reviews &rarr;</a>
            </div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-color, #0f172a);">
                <?= (float)$analytics['average_rating'] > 0 ? number_format((float)$analytics['average_rating'], 1) : "—" ?>
                <span style="font-size: 1rem; color: #f59e0b;">★</span>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted, #64748b); margin-top: 0.25rem;">
                <?= (int)$analytics['review_count'] ?> customer <?= (int)$analytics['review_count'] === 1 ? 'review' : 'reviews' ?>
            </div>
        </div>
    </div>

    <?php if ($sellerType === "food_business"): ?>
        <a href="create_food_listing.php" class="dashboard-button">
            + Create Food Listing
        </a>
    <?php elseif ($sellerType === "individual_ticket_seller"): ?>
        <a href="create_ticket_listing.php" class="dashboard-button">
            + Create Ticket Listing
        </a>
    <?php elseif ($sellerType === "event_organizer"): ?>
        <a href="create_event.php" class="dashboard-button">
            + Create Event
        </a>
        <a href="create_event_ticket.php" class="dashboard-button">
            + Create Official Ticket
        </a>
    <?php endif; ?>

    <div class="table-wrapper dashboard-table">
        <?php if (count($listings) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Listing</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Available</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Manage</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($listings as $listing): ?>
                        <tr>
                            <td><?= e($listing["title"]) ?></td>
                            <td><?= e(ucfirst($listing["listing_type"])) ?></td>
                            <td>
                                <?= e($listing["area"]) ?>,
                                <?= e($listing["city"]) ?>
                            </td>
                            <td>
                                ৳<?= number_format(
                                    (float) $listing["original_price"],
                                    2
                                ) ?>
                            </td>
                            <td>
                                <?= $listing["listing_type"] === "food"
                                    ? (int) $listing["quantity_available"]
                                    : "1" ?>
                            </td>
                            <td>
                                <?= date(
                                    "d M Y, h:i A",
                                    strtotime($listing["pickup_or_event_deadline"])
                                ) ?>
                            </td>
                            <td>
                                <span class="user-badge <?= e($listing["listing_status"]) ?>">
                                    <?= e(ucfirst($listing["listing_status"])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (in_array($listing["listing_status"], ["draft", "active", "expired"], true)): ?>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <a href="manage_listing.php?id=<?= (int) $listing["listing_id"] ?>" class="dashboard-button" style="padding: 4px 10px; font-size: 0.78rem; text-decoration: none; border-radius: 6px; line-height: 1.4;">
                                            Edit
                                        </a>
                                        <form method="post" action="manage_listing.php" onsubmit="return confirm('Remove this listing from the marketplace?');" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="listing_id" value="<?= (int) $listing["listing_id"] ?>">
                                            <button type="submit" class="reject-button" style="padding: 4px 10px; font-size: 0.78rem;">Remove</button>
                                        </form>
                                    </div>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-message">
                You have not created any listings yet.
            </p>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
