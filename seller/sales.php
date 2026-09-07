<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../login.php");
    exit;
}

$statement = $pdo->prepare("
    SELECT 
        o.order_id, 
        o.created_at, 
        o.order_status, 
        oi.item_title, 
        oi.quantity, 
        oi.subtotal, 
        p.payment_status, 
        u.full_name AS buyer_name
    FROM order_items oi 
    JOIN orders o ON o.order_id = oi.order_id 
    JOIN listings l ON l.listing_id = oi.listing_id
    JOIN users u ON u.user_id = o.buyer_id 
    LEFT JOIN payments p ON p.order_id = o.order_id
    WHERE l.seller_id = ? 
    ORDER BY o.created_at DESC
");
$statement->execute([$_SESSION["user_id"]]);
$sales = $statement->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Sales & Orders | LastCall Seller";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>Your Sales</h2>
        <p>Orders received for your active and completed listings.</p>
    </div>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Buyer</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$sales): ?>
                    <tr>
                        <td colspan="7">No sales have been made yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>
                                <strong>#<?= (int) $sale["order_id"] ?></strong><br>
                                <span style="font-size:0.78rem; opacity:0.8;"><?= e(ucfirst($sale["order_status"])) ?></span>
                            </td>
                            <td><?= e($sale["buyer_name"]) ?></td>
                            <td><?= e($sale["item_title"]) ?></td>
                            <td><?= (int) $sale["quantity"] ?></td>
                            <td><strong>৳<?= number_format((float) $sale["subtotal"], 2) ?></strong></td>
                            <td>
                                <span class="user-badge <?= e($sale["payment_status"] ?? "pending") ?>">
                                    <?= e(ucfirst($sale["payment_status"] ?? "pending")) ?>
                                </span>
                            </td>
                            <td><small><?= e(date("d M Y, h:i A", strtotime($sale["created_at"]))) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
