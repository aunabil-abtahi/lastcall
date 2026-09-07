<?php
/**
 * Shared Footer Partial — Community impact counter, logo branding, and responsive footer.
 *
 * Requires $pdo to be defined (from config/database.php).
 * Shows live "meals rescued" and "total saved" counters from completed food orders.
 */

$_footerScriptPath = $_SERVER["SCRIPT_NAME"] ?? "";
$_footerInSubdir = (bool) preg_match('#/(admin|seller|payments|tasks)/#', $_footerScriptPath);
$_base = $_footerInSubdir ? "../" : "";

$_footerMealsRescued = 0;
$_footerTotalSaved = 0.0;

if (isset($pdo)) {
    try {
        $impactQuery = $pdo->query("
            SELECT
                COALESCE(SUM(oi.quantity), 0) AS meals_rescued,
                COALESCE(SUM(oi.quantity * GREATEST(0, l.original_price - oi.unit_price)), 0) AS total_saved
            FROM order_items oi
            JOIN orders o ON o.order_id = oi.order_id
            JOIN listings l ON l.listing_id = oi.listing_id
            WHERE o.order_status = 'completed'
              AND l.listing_type = 'food'
        ");
        $impact = $impactQuery->fetch(PDO::FETCH_ASSOC);
        if ($impact) {
            $_footerMealsRescued = (int) $impact["meals_rescued"];
            $_footerTotalSaved = (float) $impact["total_saved"];
        }
    } catch (Exception $e) {
        // Silently fail — footer impact counter is non-critical
    }
}
?>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-brand-col">
                <a href="<?= $_base ?>index.php" title="LastCall">
                    <img src="<?= $_base ?>assets/images/logo-light.png" alt="LastCall" class="footer-logo-img" width="110" height="26" style="height:26px; max-height:26px; width:auto; max-width:125px; object-fit:contain; display:block; margin-bottom:14px;">
                </a>
                <p class="footer-tagline">
                    The hyper-local marketplace rescuing surplus meals and event tickets with dynamic last-minute discounts.
                </p>
                <?php if ($_footerMealsRescued > 0 || $_footerTotalSaved > 0): ?>
                    <div class="impact-badge-card">
                        <div class="impact-icon">⏳</div>
                        <div class="impact-text">
                            <span class="impact-number"><?= number_format($_footerMealsRescued) ?> Meals Rescued</span>
                            <span class="impact-sub">৳<?= number_format($_footerTotalSaved, 0) ?> Saved</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer-nav-col">
                <h4>Marketplace</h4>
                <ul>
                    <li><a href="<?= $_base ?>index.php">Browse All Deals</a></li>
                    <li><a href="<?= $_base ?>index.php?type=food">🍽️ Food Rescue</a></li>
                    <li><a href="<?= $_base ?>index.php?type=ticket">🎟️ Event Tickets</a></li>
                    <li><a href="<?= $_base ?>recommendations.php">✨ For You</a></li>
                    <li><a href="<?= $_base ?>leaderboard.php">🏆 Leaderboard</a></li>
                </ul>
            </div>

            <div class="footer-nav-col">
                <h4>For Vendors</h4>
                <ul>
                    <li><a href="<?= $_base ?>seller_apply.php">Become a Seller</a></li>
                    <li><a href="<?= $_base ?>seller/dashboard.php">Seller Portal</a></li>
                    <li><a href="<?= $_base ?>seller/create_food_listing.php">Post Surplus Food</a></li>
                    <li><a href="<?= $_base ?>seller/create_ticket_listing.php">List Event Tickets</a></li>
                </ul>
            </div>

            <div class="footer-nav-col">
                <h4>Trust &amp; Safety</h4>
                <p class="footer-safety-text">
                    All event tickets are cryptographically verified. Food pickup is strictly timed to guarantee freshness.
                </p>
                <div class="verified-pill">
                    🛡️ Verified Guarantee
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date("Y") ?> LastCall. All rights reserved.</p>
            <p class="footer-built">Engineered for last-minute savings &amp; neighborhood sustainability.</p>
        </div>
    </div>
</footer>

</body>
</html>
