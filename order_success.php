<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$orderId = (int) ($_GET["order_id"] ?? 0);
$orderQuery = $pdo->prepare("
    SELECT o.order_id, o.total_amount, o.completed_at,
           p.transaction_reference, oi.item_title, oi.quantity,
           l.listing_type, l.pickup_or_event_deadline,
           loc.city, loc.area
    FROM orders o
    JOIN payments p ON p.order_id = o.order_id
    JOIN order_items oi ON oi.order_id = o.order_id
    JOIN listings l ON l.listing_id = oi.listing_id
    JOIN locations loc ON loc.location_id = l.location_id
    WHERE o.order_id = ?
      AND o.buyer_id = ?
      AND o.order_status = 'completed'
    LIMIT 1
");
$orderQuery->execute([$orderId, $_SESSION["user_id"]]);
$order = $orderQuery->fetch(PDO::FETCH_ASSOC);

$pageTitle = "Order Complete | LastCall";
require_once __DIR__ . "/includes/header.php";
?>
<main class="auth-page">
    <section class="form-card">
        <?php if (!$order): ?>
            <h1>Order not found</h1>
            <p class="form-intro">This completed order is unavailable.</p>
        <?php else: ?>
            <div class="success-icon">✓</div>
            <h1>Order complete</h1>
            <p class="form-intro">
                <?= $order["listing_type"] === "ticket"
                    ? "Your order is confirmed and payment has been verified through SSLCOMMERZ Sandbox. Ticket ownership has been transferred."
                    : "Your order is confirmed and payment has been verified through SSLCOMMERZ Sandbox. The food is reserved for pickup." ?>
            </p>
            <div class="checkout-summary">
                <p><strong>Order:</strong> #<?= (int) $order["order_id"] ?></p>
                <p><strong>Item:</strong> <?= e($order["item_title"]) ?></p>
                <p><strong>Quantity:</strong> <?= (int) $order["quantity"] ?></p>
                <p><strong>Total:</strong> ৳<?= number_format((float) $order["total_amount"], 2) ?></p>
                <p><strong>Reference:</strong> <?= e($order["transaction_reference"]) ?></p>
                <?php if (!empty($order["pickup_or_event_deadline"])): ?>
                    <p><strong><?= $order["listing_type"] === "ticket" ? "Event Date:" : "Pickup Before:" ?></strong>
                       <?= date("M j, Y g:i A", strtotime($order["pickup_or_event_deadline"])) ?></p>
                <?php endif; ?>
                <?php if (!empty($order["area"]) || !empty($order["city"])): ?>
                    <p><strong><?= $order["listing_type"] === "ticket" ? "Venue:" : "Pickup Location:" ?></strong>
                       <?= e($order["area"]) ?>, <?= e($order["city"]) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <a class="primary-link" href="index.php">Browse more deals</a>
    </section>
</main>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
