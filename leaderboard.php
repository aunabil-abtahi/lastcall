<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

$rows = $pdo->query("
    SELECT u.full_name,
           COUNT(DISTINCT o.order_id) AS order_count,
           COALESCE(SUM(GREATEST((l.original_price * oi.quantity) - oi.subtotal, 0)), 0) AS saved
    FROM users u
    JOIN orders o ON o.buyer_id = u.user_id
    JOIN order_items oi ON oi.order_id = o.order_id
    JOIN listings l ON l.listing_id = oi.listing_id
    WHERE o.order_status = 'completed' AND l.listing_type = 'food'
    GROUP BY u.user_id, u.full_name
    ORDER BY saved DESC, order_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Savings Leaderboard | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>Food Savings Leaderboard</h2>
        <p>Top buyers by money saved on completed food-rescue orders.</p>
    </div>
    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Buyer</th>
                    <th>Completed Orders</th>
                    <th>Saved</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="4">No completed food orders yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $row): ?>
                        <tr>
                            <td>#<?= $i + 1 ?></td>
                            <td><?= e($row["full_name"]) ?></td>
                            <td><?= $row["order_count"] ?></td>
                            <td>৳<?= number_format((float) $row["saved"], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
