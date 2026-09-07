<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "admin") {
    header("Location: ../index.php");
    exit;
}

$status = $_GET["status"] ?? "";
$sql = "
    SELECT 
        o.order_id,
        o.total_amount,
        o.order_status,
        o.created_at,
        p.payment_status,
        p.transaction_reference,
        u.full_name,
        oi.item_title 
    FROM orders o 
    JOIN payments p ON p.order_id = o.order_id 
    JOIN users u ON u.user_id = o.buyer_id 
    JOIN order_items oi ON oi.order_id = o.order_id
";

$args = [];
if (in_array($status, ["pending", "paid", "failed", "cancelled"], true)) {
    $sql .= " WHERE p.payment_status = ?";
    $args[] = $status;
}
$sql .= " ORDER BY o.created_at DESC";

$q = $pdo->prepare($sql);
$q->execute($args);
$orders = $q->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Orders & Payments | LastCall Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>Orders &amp; Payments Audit</h2>
        <p>Real-time settlement and payment gateway verification ledger.</p>
    </div>

    <!-- Filter Pills -->
    <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <a href="orders.php" class="dashboard-button" style="padding:0.4rem 0.85rem; font-size:0.85rem; text-decoration:none; <?= empty($status) ? 'opacity:1; font-weight:700;' : 'opacity:0.75;' ?>">All Orders</a>
        <a href="orders.php?status=paid" class="dashboard-button" style="padding:0.4rem 0.85rem; font-size:0.85rem; text-decoration:none; <?= $status === 'paid' ? 'opacity:1; font-weight:700;' : 'opacity:0.75;' ?>">Paid</a>
        <a href="orders.php?status=pending" class="dashboard-button" style="padding:0.4rem 0.85rem; font-size:0.85rem; text-decoration:none; <?= $status === 'pending' ? 'opacity:1; font-weight:700;' : 'opacity:0.75;' ?>">Pending</a>
        <a href="orders.php?status=failed" class="dashboard-button" style="padding:0.4rem 0.85rem; font-size:0.85rem; text-decoration:none; <?= $status === 'failed' ? 'opacity:1; font-weight:700;' : 'opacity:0.75;' ?>">Failed</a>
        <a href="orders.php?status=cancelled" class="dashboard-button" style="padding:0.4rem 0.85rem; font-size:0.85rem; text-decoration:none; <?= $status === 'cancelled' ? 'opacity:1; font-weight:700;' : 'opacity:0.75;' ?>">Cancelled</a>
    </div>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Buyer</th>
                    <th>Listing Item</th>
                    <th>Total Amount</th>
                    <th>Payment Status</th>
                    <th>Transaction Ref</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr>
                        <td colspan="7">No orders found matching the filter criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>
                                <strong>#<?= (int) $o["order_id"] ?></strong><br>
                                <span style="font-size:0.78rem; opacity:0.8;"><?= e(ucfirst($o["order_status"])) ?></span>
                            </td>
                            <td><?= e($o["full_name"]) ?></td>
                            <td><?= e($o["item_title"]) ?></td>
                            <td><strong>৳<?= number_format((float) $o["total_amount"], 2) ?></strong></td>
                            <td>
                                <span class="user-badge <?= e($o["payment_status"]) ?>">
                                    <?= e(ucfirst($o["payment_status"])) ?>
                                </span>
                            </td>
                            <td><code><?= e($o["transaction_reference"] ?? "—") ?></code></td>
                            <td><small><?= e(date("d M Y, h:i A", strtotime($o["created_at"]))) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
