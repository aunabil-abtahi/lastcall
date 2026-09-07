<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

runMarketplaceMaintenance($pdo);

// --- Filters ---
$search = trim($_GET["q"] ?? "");
$listingType = $_GET["type"] ?? "";
$city = trim($_GET["city"] ?? "");
$area = trim($_GET["area"] ?? "");
$foodCategory = $_GET["category"] ?? "";
$minPrice = $_GET["min_price"] ?? "";
$maxPrice = $_GET["max_price"] ?? "";
$minDiscount = $_GET["min_discount"] ?? "";
$sortBy = $_GET["sort"] ?? "deadline";

// Auto-default city for logged-in users on first visit (no filters set)
if (isLoggedIn() && $city === "" && $area === "" && !isset($_GET["q"])) {
    $userLocQuery = $pdo->prepare("
        SELECT loc.city FROM users u
        JOIN locations loc ON loc.location_id = u.location_id
        WHERE u.user_id = ? LIMIT 1
    ");
    $userLocQuery->execute([$_SESSION["user_id"]]);
    $autoCity = $userLocQuery->fetchColumn();
    if ($autoCity) $city = $autoCity;
}

// Build dynamic WHERE clauses
$filters = [];
$parameters = [];

if ($search !== "") {
    $filters[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $parameters[] = "%$search%";
    $parameters[] = "%$search%";
}
if (in_array($listingType, ["food", "ticket"], true)) {
    $filters[] = "l.listing_type = ?";
    $parameters[] = $listingType;
}
if ($city !== "") {
    $filters[] = "loc.city LIKE ?";
    $parameters[] = "%$city%";
}
if ($area !== "") {
    $filters[] = "loc.area LIKE ?";
    $parameters[] = "%$area%";
}
if (in_array($foodCategory, ["meal", "bakery", "beverage", "snack", "other"], true)) {
    $filters[] = "l.listing_type = 'food' AND f.food_category = ?";
    $parameters[] = $foodCategory;
}
if (is_numeric($minPrice) && (float) $minPrice >= 0) {
    $filters[] = "l.original_price >= ?";
    $parameters[] = (float) $minPrice;
}
if (is_numeric($maxPrice) && (float) $maxPrice > 0) {
    $filters[] = "l.original_price <= ?";
    $parameters[] = (float) $maxPrice;
}

// Sort order
$orderClause = match ($sortBy) {
    "price_low" => "l.original_price ASC",
    "price_high" => "l.original_price DESC",
    "discount" => "discount_percent DESC, l.pickup_or_event_deadline ASC",
    default => "l.pickup_or_event_deadline ASC",
};

$sql = "
    SELECT
        l.listing_id,
        l.listing_type,
        l.title,
        l.description,
        l.original_price,
        l.pickup_or_event_deadline,
        loc.city,
        loc.area,
        f.quantity_available,
        f.food_category,

        COALESCE(
            (
                SELECT dr.discount_percent
                FROM discount_rules dr
                WHERE dr.listing_id = l.listing_id
                  AND dr.threshold_minutes >= TIMESTAMPDIFF(
                      MINUTE, NOW(), l.pickup_or_event_deadline
                  )
                ORDER BY dr.threshold_minutes ASC
                LIMIT 1
            ),
            0
        ) AS discount_percent,

        TIMESTAMPDIFF(
            MINUTE, NOW(), l.pickup_or_event_deadline
        ) AS minutes_left

    FROM listings l
    JOIN locations loc ON l.location_id = loc.location_id
    LEFT JOIN food_listing_details f ON l.listing_id = f.listing_id
    LEFT JOIN ticket_listings tl ON l.listing_id = tl.listing_id
    LEFT JOIN tickets t ON tl.ticket_id = t.ticket_id

    WHERE l.listing_status = 'active'
      AND l.pickup_or_event_deadline > NOW()
      AND (
          (l.listing_type = 'food' AND f.quantity_available > 0)
          OR (
              l.listing_type = 'ticket'
              AND t.verification_status = 'verified'
              AND t.availability_status = 'available'
          )
      )
    " . ($filters ? " AND " . implode(" AND ", $filters) : "") . "
    ORDER BY $orderClause
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parameters);
$allListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Apply post-query discount filter (discount is computed in SELECT, not filterable in WHERE easily)
if (is_numeric($minDiscount) && (int) $minDiscount > 0) {
    $allListings = array_filter($allListings, fn($l) => (float) $l["discount_percent"] >= (int) $minDiscount);
}

// Separate "Last Chance" listings (< 60 minutes left) from regular listings
$lastChanceListings = array_filter($allListings, fn($l) => (int) $l["minutes_left"] <= 60 && (int) $l["minutes_left"] > 0);
$regularListings = array_filter($allListings, fn($l) => (int) $l["minutes_left"] > 60);

// Load distinct cities and areas for filter dropdowns
$citiesQuery = $pdo->query("SELECT DISTINCT city FROM locations ORDER BY city");
$allCities = $citiesQuery->fetchAll(PDO::FETCH_COLUMN);

$areasQuery = $pdo->query("SELECT DISTINCT area FROM locations ORDER BY area");
$allAreas = $areasQuery->fetchAll(PDO::FETCH_COLUMN);

$hasActiveFilters = ($search !== "" || $listingType !== "" || $city !== "" || $area !== "" || $foodCategory !== "" || $minPrice !== "" || $maxPrice !== "" || $minDiscount !== "" || $sortBy !== "deadline");

$pageTitle = "LastCall | Last Minute Marketplace";
require_once __DIR__ . "/includes/header.php";
?>

<section class="hero">
    <div class="hero-pill">⏳ Hyper-Local Last-Minute Marketplace</div>
    <h1>Rescue Surplus Meals &amp; Event Tickets <span class="gradient-text">Before Time Runs Out.</span></h1>
    <p class="hero-subtitle">
        High-quality food from local kitchens and verified event tickets at up to 70% discount — while fighting waste in your neighborhood.
    </p>
    <div class="hero-quick-filters">
        <a href="index.php" class="hero-pill-btn <?= empty($listingType) ? 'active' : '' ?>">🔥 All Deals</a>
        <a href="index.php?type=food" class="hero-pill-btn <?= $listingType === 'food' ? 'active' : '' ?>">🍽️ Food Rescue</a>
        <a href="index.php?type=ticket" class="hero-pill-btn <?= $listingType === 'ticket' ? 'active' : '' ?>">🎟️ Event Tickets</a>
        <a href="#last-chance" class="hero-pill-btn expiring">⚡ Expiring Soon</a>
    </div>
</section>

<main class="container">
    <?php if (isLoggedIn() && currentUserRole() === "admin"): ?>
        <div class="admin-demo-toolbar">
            <div class="demo-toolbar-info">
                <span class="demo-pulse-dot"></span>
                <strong>Faculty Demo Mode:</strong> Ready to present? Populate fresh surplus food &amp; verified tickets.
            </div>
            <div class="demo-toolbar-actions">
                <form action="admin/seed_demo.php" method="POST" style="display:inline; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="seed">
                    <input type="hidden" name="return_to" value="index.php">
                    <button type="submit" class="btn-demo-action seed-btn" title="Add 11 curated demo listings">⚡ Seed Demo Listings</button>
                </form>
                <form action="admin/seed_demo.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Clear all generated demo listings from marketplace?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="return_to" value="index.php">
                    <button type="submit" class="btn-demo-action clear-btn" title="Clear demo items to show empty state">🧹 Clear Demo Data</button>
                </form>
                <a href="admin/seed_demo.php" class="btn-demo-action studio-btn">🛠️ Data Studio</a>
            </div>
        </div>
    <?php endif; ?>

    <form method="get" class="filter-form">
        <input name="q" value="<?= e($search) ?>" placeholder="Search deals">
        <select name="type">
            <option value="">All types</option>
            <option value="food" <?= $listingType === "food" ? "selected" : "" ?>>Food</option>
            <option value="ticket" <?= $listingType === "ticket" ? "selected" : "" ?>>Tickets</option>
        </select>
        <select name="category">
            <option value="">All categories</option>
            <?php foreach (["meal", "bakery", "beverage", "snack", "other"] as $cat): ?>
                <option value="<?= $cat ?>" <?= $foodCategory === $cat ? "selected" : "" ?>><?= ucfirst($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="city">
            <option value="">All cities</option>
            <?php foreach ($allCities as $c): ?>
                <option value="<?= e($c) ?>" <?= $city === $c ? "selected" : "" ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="area">
            <option value="">All areas</option>
            <?php foreach ($allAreas as $a): ?>
                <option value="<?= e($a) ?>" <?= $area === $a ? "selected" : "" ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="filter-group">
            <label>৳ Min</label>
            <input name="min_price" type="number" step="0.01" min="0" value="<?= e($minPrice) ?>" placeholder="0" style="width:80px">
        </div>
        <div class="filter-group">
            <label>৳ Max</label>
            <input name="max_price" type="number" step="0.01" min="0" value="<?= e($maxPrice) ?>" placeholder="∞" style="width:80px">
        </div>
        <select name="min_discount">
            <option value="">Any discount</option>
            <option value="10" <?= $minDiscount === "10" ? "selected" : "" ?>>≥ 10%</option>
            <option value="25" <?= $minDiscount === "25" ? "selected" : "" ?>>≥ 25%</option>
            <option value="40" <?= $minDiscount === "40" ? "selected" : "" ?>>≥ 40%</option>
            <option value="50" <?= $minDiscount === "50" ? "selected" : "" ?>>≥ 50%</option>
        </select>
        <select name="sort">
            <option value="deadline" <?= $sortBy === "deadline" ? "selected" : "" ?>>Ending soonest</option>
            <option value="price_low" <?= $sortBy === "price_low" ? "selected" : "" ?>>Price: Low → High</option>
            <option value="price_high" <?= $sortBy === "price_high" ? "selected" : "" ?>>Price: High → Low</option>
            <option value="discount" <?= $sortBy === "discount" ? "selected" : "" ?>>Biggest discount</option>
        </select>
        <button type="submit">Search</button>
        <?php if ($hasActiveFilters): ?><a href="index.php">Clear</a><?php endif; ?>
    </form>

    <?php if (count($lastChanceListings) > 0): ?>
        <div class="urgency-section" id="last-chance">
            <div class="section-heading">
                <h2>⏰ Last Chance <span class="urgency-badge">EXPIRING SOON</span></h2>
                <p>These deals expire within the next hour — grab them now!</p>
            </div>
            <div class="listing-grid">
                <?php foreach ($lastChanceListings as $listing): ?>
                    <?php
                        $discount = (float) $listing["discount_percent"];
                        $originalPrice = (float) $listing["original_price"];
                        $currentPrice = $originalPrice * (1 - ($discount / 100));
                        $isFood = $listing["listing_type"] === "food";
                        $timeLeft = $listing["minutes_left"] . " minutes left";
                    ?>
                    <article class="card">
                        <span class="badge <?= $isFood ? "" : "ticket" ?>">
                            <?= $isFood ? "Food Rescue" : "Event Ticket" ?>
                        </span>
                        <h3><?= e($listing["title"]) ?></h3>
                        <p class="description"><?= e($listing["description"] ?? "No description available.") ?></p>
                        <p class="location">📍 <?= e($listing["area"]) ?>, <?= e($listing["city"]) ?></p>
                        <p class="meta" style="color:#dc3545;font-weight:bold">🔥 <?= e($timeLeft) ?></p>
                        <?php if ($isFood && isset($listing["quantity_available"])): ?>
                            <p class="meta">🍽 <?= (int) $listing["quantity_available"] ?> available</p>
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
                        <a class="card-link" href="listing.php?id=<?= (int) $listing["listing_id"] ?>">View Deal</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-heading">
        <h2>Last Chance Deals</h2>
        <p>Active listings ordered by the nearest deadline.</p>
    </div>

    <?php if (count($regularListings) > 0): ?>
        <div class="listing-grid">
            <?php foreach ($regularListings as $listing): ?>
                <?php
                    $discount = (float) $listing["discount_percent"];
                    $originalPrice = (float) $listing["original_price"];
                    $currentPrice = $originalPrice * (1 - ($discount / 100));
                    $isFood = $listing["listing_type"] === "food";
                    if ($listing["minutes_left"] <= 60) {
                        $timeLeft = $listing["minutes_left"] . " minutes left";
                    } else {
                        $timeLeft = ceil($listing["minutes_left"] / 60) . " hours left";
                    }
                ?>
                <article class="card">
                    <span class="badge <?= $isFood ? "" : "ticket" ?>">
                        <?= $isFood ? "Food Rescue" : "Event Ticket" ?>
                    </span>
                    <h3><?= e($listing["title"]) ?></h3>
                    <p class="description"><?= e($listing["description"] ?? "No description available.") ?></p>
                    <p class="location">📍 <?= e($listing["area"]) ?>, <?= e($listing["city"]) ?></p>
                    <p class="meta">⏰ <?= e($timeLeft) ?></p>
                    <?php if ($isFood && isset($listing["quantity_available"])): ?>
                        <p class="meta">🍽 <?= (int) $listing["quantity_available"] ?> available</p>
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
                    <a class="card-link" href="listing.php?id=<?= (int) $listing["listing_id"] ?>">View Deal</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php elseif (count($lastChanceListings) === 0): ?>
        <p class="empty-message">
            No active listings are available right now.
        </p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
