<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

requireLogin();

$q = $pdo->prepare("
    SELECT o.order_id, o.total_amount, o.order_status, o.completed_at,
           p.payment_status, p.transaction_reference,
           oi.item_title, oi.quantity, l.listing_type,
           r.review_id
    FROM orders o
    JOIN payments p ON p.order_id = o.order_id
    JOIN order_items oi ON oi.order_id = o.order_id
    JOIN listings l ON l.listing_id = oi.listing_id
    LEFT JOIN reviews r ON r.order_id = o.order_id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$q->execute([$_SESSION["user_id"]]);
$orders = $q->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Orders | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>My Orders</h2>
        <p>Your completed and pending purchases.</p>
    </div>
    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Item</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Transaction</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int) $o["order_id"] ?><br><span><?= e($o["order_status"]) ?></span></td>
                        <td><?= e($o["item_title"]) ?> (<?= (int) $o["quantity"] ?>)</td>
                        <td>৳<?= number_format((float) $o["total_amount"], 2) ?></td>
                        <td><?= e($o["payment_status"]) ?></td>
                        <td><?= e($o["transaction_reference"] ?? "-") ?></td>
                        <td>
                            <?php if ($o["order_status"] === "completed" && !$o["review_id"]): ?>
                                <a href="review.php?order_id=<?= (int) $o["order_id"] ?>">Write review</a>
                            <?php elseif ($o["review_id"]): ?>
                                Submitted
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$orders): ?>
            <p class="empty-message">You have not placed any orders yet.</p>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
