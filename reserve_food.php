<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

runMarketplaceMaintenance($pdo);

$buyerId = $_SESSION["user_id"];
$listingId = (int) ($_GET["listing_id"] ?? $_POST["listing_id"] ?? 0);
$error = "";

$listingQuery = $pdo->prepare("
    SELECT l.listing_id, l.seller_id, l.title, l.original_price, l.pickup_or_event_deadline,
           f.quantity_available
    FROM listings l
    JOIN food_listing_details f ON f.listing_id = l.listing_id
    WHERE l.listing_id = ?
      AND l.listing_type = 'food'
      AND l.listing_status = 'active'
      AND l.pickup_or_event_deadline > NOW()
      AND f.quantity_available > 0
    LIMIT 1
");
$listingQuery->execute([$listingId]);
$listing = $listingQuery->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST" && $listing) {
    require_csrf();
    $quantity = (int) ($_POST["quantity"] ?? 0);

    if ((int) $listing["seller_id"] === $buyerId) {
        $error = "You cannot reserve your own listing.";
    } elseif ($quantity < 1) {
        $error = "Choose at least one portion.";
    } else {
        try {
            $pdo->beginTransaction();

            $lockedListing = $pdo->prepare("
                SELECT l.listing_id, l.seller_id, l.title, l.original_price,
                       l.pickup_or_event_deadline, f.quantity_available,
                       COALESCE((
                           SELECT dr.discount_percent
                           FROM discount_rules dr
                           WHERE dr.listing_id = l.listing_id
                             AND dr.threshold_minutes >= TIMESTAMPDIFF(
                                 MINUTE, NOW(), l.pickup_or_event_deadline
                             )
                           ORDER BY dr.threshold_minutes ASC
                           LIMIT 1
                       ), 0) AS discount_percent
                FROM listings l
                JOIN food_listing_details f ON f.listing_id = l.listing_id
                WHERE l.listing_id = ?
                  AND l.listing_type = 'food'
                  AND l.listing_status = 'active'
                  AND l.pickup_or_event_deadline > NOW()
                FOR UPDATE
            ");
            $lockedListing->execute([$listingId]);
            $current = $lockedListing->fetch(PDO::FETCH_ASSOC);

            if (!$current || (int) $current["quantity_available"] < $quantity) {
                $pdo->rollBack();
                $error = "Not enough portions remain available.";
            } else {
                $discountPercent = (float) $current["discount_percent"];
                $unitPrice = round((float) $current["original_price"] * (1 - ($discountPercent / 100)), 2);
                $totalPrice = round($unitPrice * $quantity, 2);

                $createReservation = $pdo->prepare("
                    INSERT INTO reservations (
                        buyer_id, expires_at, status, total_reserved_price
                    ) VALUES (
                        ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'active', ?
                    )
                ");
                $createReservation->execute([$buyerId, $totalPrice]);
                $reservationId = (int) $pdo->lastInsertId();

                $createItem = $pdo->prepare("
                    INSERT INTO reservation_items (
                        reservation_id, listing_id, item_title,
                        quantity, unit_price, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $createItem->execute([
                    $reservationId,
                    $listingId,
                    $current["title"],
                    $quantity,
                    $unitPrice,
                    $totalPrice
                ]);

                $updateAvailable = $pdo->prepare("
                    UPDATE food_listing_details
                    SET quantity_available = quantity_available - ?
                    WHERE listing_id = ?
                ");
                $updateAvailable->execute([$quantity, $listingId]);

                $updateListingStatus = $pdo->prepare("
                    UPDATE listings l
                    JOIN food_listing_details f ON f.listing_id = l.listing_id
                    SET l.listing_status = CASE
                        WHEN f.quantity_available > 0 THEN 'active'
                        ELSE 'sold_out'
                    END
                    WHERE l.listing_id = ?
                ");
                $updateListingStatus->execute([$listingId]);

                $pdo->commit();
                header("Location: checkout.php?reservation_id=" . $reservationId);
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Could not create the reservation. Please try again.";
        }
    }
}

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Reserve Food | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page" style="padding: 40px 20px 80px;">
    <section class="form-card" style="max-width: 520px; margin: 0 auto; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <?php if (!$listing): ?>
            <h1 style="font-size: 1.5rem; color: var(--brand-navy); margin-bottom: 8px;">Food unavailable</h1>
            <p class="form-intro" style="color: var(--text-muted); margin-bottom: 20px;">This listing is sold out, expired, or unavailable.</p>
            <a class="primary-button" href="index.php" style="display: inline-block; text-decoration: none;">&larr; Back to deals</a>
        <?php else: ?>
            <h1 style="font-size: 1.6rem; color: var(--brand-navy); margin-bottom: 8px;">Reserve Food</h1>
            <p class="form-intro" style="color: var(--text-muted); margin-bottom: 20px;">
                <strong><?= e($listing["title"]) ?></strong> has <?= (int) $listing["quantity_available"] ?> portions available.
                Your reservation is held for <strong>5 minutes</strong>.
            </p>
            <?php if ($error !== ""): ?><div class="alert alert-error" style="margin-bottom: 16px;"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" action="reserve_food.php">
                <?= csrf_field() ?>
                <input type="hidden" name="listing_id" value="<?= (int) $listingId ?>">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="quantity" style="display: block; font-weight: 600; margin-bottom: 6px;">Portions to Reserve</label>
                    <input type="number" id="quantity" name="quantity" min="1" max="<?= (int) $listing["quantity_available"] ?>" value="1" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
                </div>
                <button type="submit" class="primary-button" style="width: 100%; padding: 12px; font-weight: 700; cursor: pointer;">Reserve for 5 Minutes</button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
