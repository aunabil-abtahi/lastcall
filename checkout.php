<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

requireLogin();

runMarketplaceMaintenance($pdo);

$buyerId = $_SESSION["user_id"];
$reservationId = (int) ($_GET["reservation_id"] ?? $_POST["reservation_id"] ?? 0);
$error = $_SESSION["payment_error"] ?? "";
unset($_SESSION["payment_error"]);

$reservationQuery = $pdo->prepare("
    SELECT
        r.reservation_id,
        r.quantity,
        r.reserved_price,
        r.expires_at,
        r.reservation_status,
        r.ticket_id,
        l.listing_id,
        l.listing_type,
        l.title,
        l.pickup_or_event_deadline,
        loc.city,
        loc.area
    FROM reservations r
    JOIN listings l ON l.listing_id = r.listing_id
    JOIN locations loc ON loc.location_id = l.location_id
    WHERE r.reservation_id = ?
      AND r.buyer_id = ?
    LIMIT 1
");
$reservationQuery->execute([$reservationId, $buyerId]);
$reservation = $reservationQuery->fetch(PDO::FETCH_ASSOC);

$pageTitle = "Checkout | LastCall";
require_once __DIR__ . "/includes/header.php";
?>
<main class="auth-page">
    <section class="form-card">
        <h1>Checkout</h1>

        <?php if (!$reservation): ?>
            <div class="alert alert-error">This reservation does not exist or does not belong to you.</div>
            <a class="primary-link" href="index.php">Back to deals</a>
        <?php elseif ($reservation["reservation_status"] !== "active" || strtotime($reservation["expires_at"]) <= time()): ?>
            <div class="alert alert-error">This reservation has expired or was already completed.</div>
            <a class="primary-link" href="index.php">Back to deals</a>
        <?php else: ?>
            <?php if ($error !== ""): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <div class="checkout-summary">
                <h2><?= e($reservation["title"]) ?></h2>
                <p>
                    <?= $reservation["ticket_id"] !== null
                        ? "1 ticket"
                        : (int) $reservation["quantity"] . " portion(s)" ?>
                </p>
                <p>📍 <?= e($reservation["area"]) ?>, <?= e($reservation["city"]) ?></p>
                <p class="reservation-expiry">Reserved until: <?= date("h:i:s A", strtotime($reservation["expires_at"])) ?></p>
                <strong>৳<?= number_format((float) $reservation["reserved_price"], 2) ?></strong>
            </div>
            <p class="form-intro">You will be redirected to the SSLCOMMERZ Sandbox. No real money is charged.</p>
            <form method="POST" action="payment_initiate.php">
                <?= csrf_field() ?>
                <input type="hidden" name="reservation_id" value="<?= (int) $reservationId ?>">
                <button type="submit" class="primary-button">Pay with SSLCOMMERZ Sandbox</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
