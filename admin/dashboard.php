<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/maintenance.php";

if (!isLoggedIn() || currentUserRole() !== "admin") {
    header("Location: ../index.php");
    exit;
}

runMarketplaceMaintenance($pdo);

$flashMessage = $_SESSION["admin_message"] ?? "";
$flashType = $_SESSION["admin_message_type"] ?? "success";
unset($_SESSION["admin_message"], $_SESSION["admin_message_type"]);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

// 1. Executive Platform KPIs
// GMV
$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status = 'completed'");
$gmv = (float) $stmt->fetchColumn();

// Completed Orders Count
$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'completed'");
$completedOrdersCount = (int) $stmt->fetchColumn();

// Total Orders Count
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrdersCount = (int) $stmt->fetchColumn();

// Rescued Items (Meals & Tickets sold)
$stmt = $pdo->query("
    SELECT COALESCE(SUM(oi.quantity), 0) 
    FROM order_items oi 
    JOIN orders o ON o.order_id = oi.order_id 
    WHERE o.order_status = 'completed'
");
$rescuedItems = (int) $stmt->fetchColumn();

// Sellers: Approved, Pending, Rejected
$stmt = $pdo->query("SELECT COUNT(*) FROM seller_profiles WHERE verification_status = 'approved'");
$approvedSellers = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM seller_profiles WHERE verification_status = 'pending'");
$pendingSellers = (int) $stmt->fetchColumn();

// Tickets: Verified, Pending
$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE verification_status = 'verified'");
$verifiedTickets = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE verification_status = 'pending'");
$pendingTickets = (int) $stmt->fetchColumn();

// Users Breakdown
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'");
$buyerCount = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'seller'");
$sellerCount = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status IN ('suspended', 'blocked')");
$suspendedCount = (int) $stmt->fetchColumn();

// Moderation Reports
$stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE report_status IN ('open', 'reviewing')");
$openReports = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM reports");
$totalReports = (int) $stmt->fetchColumn();

// Active Listings Breakdown
$stmt = $pdo->query("SELECT COUNT(*) FROM listings WHERE listing_type = 'food' AND listing_status = 'active'");
$activeFoodListings = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM listings WHERE listing_type = 'ticket' AND listing_status = 'active'");
$activeTicketListings = (int) $stmt->fetchColumn();

// 2. Recent Platform Orders (Top 5)
$ordersStmt = $pdo->query("
    SELECT o.order_id, o.total_amount, o.order_status, o.created_at,
           COALESCE(p.payment_status, 'unpaid') AS payment_status,
           b.full_name AS buyer_name,
           (
               SELECT sp.business_name 
               FROM order_items oi2 
               JOIN listings l2 ON l2.listing_id = oi2.listing_id 
               JOIN seller_profiles sp ON sp.seller_profile_id = l2.seller_id 
               WHERE oi2.order_id = o.order_id 
               LIMIT 1
           ) AS seller_name
    FROM orders o
    JOIN users b ON b.user_id = o.buyer_id
    LEFT JOIN payments p ON p.order_id = o.order_id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$recentOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Urgent Moderation Reports (Top 5)
$reportsStmt = $pdo->query("
    SELECT r.report_id, r.report_reason, r.report_status, r.created_at,
           rep.full_name AS reporter_name,
           u.full_name AS reported_user_name,
           l.listing_id,
           l.title AS listing_title
    FROM reports r
    JOIN users rep ON rep.user_id = r.reporter_id
    LEFT JOIN listings l ON l.listing_id = r.listing_id
    LEFT JOIN users u ON u.user_id = r.reported_user_id
    ORDER BY 
        CASE r.report_status 
            WHEN 'open' THEN 1 
            WHEN 'reviewing' THEN 2 
            ELSE 3 
        END, 
        r.created_at DESC
    LIMIT 5
");
$recentReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Admin Platform Dashboard | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <!-- Section Heading -->
    <div class="section-heading" style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="font-size: 1.85rem; font-weight: 800; color: var(--brand-navy); margin-bottom: 6px;">
                    📊 Platform Operations Center
                </h2>
                <p style="color: var(--text-secondary); margin: 0; font-size: 0.95rem;">
                    Real-time marketplace analytics, vendor verification, dispute resolution, and system controls.
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <form action="seed_demo.php" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="seed">
                    <input type="hidden" name="return_to" value="dashboard.php">
                    <button type="submit" class="btn-demo-action seed-btn" title="Add fresh demo inventory">
                        ⚡ Quick Seed Demo
                    </button>
                </form>
                <a href="seed_demo.php" class="btn-demo-action studio-btn">
                    🛠️ Demo Studio
                </a>
            </div>
        </div>
    </div>

    <?php if ($flashMessage !== ""): ?>
        <div class="alert <?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>" style="margin-bottom: 24px;">
            <?= e($flashMessage) ?>
        </div>
    <?php endif; ?>

    <!-- 6 Executive Platform KPI Cards -->
    <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 32px;">
        <!-- Card 1: GMV -->
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); text-align: left; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Gross Volume (GMV)</span>
                    <span style="font-size: 1.15rem;">💰</span>
                </div>
                <div class="kpi-value" style="font-size: 1.85rem; font-weight: 800; color: var(--brand-navy); margin-bottom: 4px;">
                    ৳<?= number_format($gmv, 2) ?>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span><?= number_format($completedOrdersCount) ?> completed orders</span>
                <a href="orders.php" style="color: var(--brand-coral); font-weight: 600; text-decoration: none; font-size: 0.78rem;">Orders &rarr;</a>
            </div>
        </div>

        <!-- Card 2: Rescued Items -->
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); text-align: left; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Items Rescued</span>
                    <span style="font-size: 1.15rem;">🌱</span>
                </div>
                <div class="kpi-value" style="font-size: 1.85rem; font-weight: 800; color: var(--brand-emerald-dark); margin-bottom: 4px;">
                    <?= number_format($rescuedItems) ?>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span><?= $activeFoodListings ?> active food deals</span>
                <span class="user-badge" style="background: #ecfdf5; color: #047857; font-size: 10px; padding: 2px 6px;">Eco Impact</span>
            </div>
        </div>

        <!-- Card 3: Vendor Network -->
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); text-align: left; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Approved Vendors</span>
                    <span style="font-size: 1.15rem;">🏪</span>
                </div>
                <div class="kpi-value" style="font-size: 1.85rem; font-weight: 800; color: var(--brand-navy); margin-bottom: 4px;">
                    <?= $approvedSellers ?>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <?php if ($pendingSellers > 0): ?>
                    <span style="color: #b45309; font-weight: 700;">⚠️ <?= $pendingSellers ?> pending</span>
                <?php else: ?>
                    <span style="color: #047857;">All approved</span>
                <?php endif; ?>
                <a href="sellers.php" style="color: var(--brand-coral); font-weight: 600; text-decoration: none; font-size: 0.78rem;">Review &rarr;</a>
            </div>
        </div>

        <!-- Card 4: Ticket Inventory -->
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); text-align: left; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Verified Tickets</span>
                    <span style="font-size: 1.15rem;">🎟️</span>
                </div>
                <div class="kpi-value" style="font-size: 1.85rem; font-weight: 800; color: var(--brand-navy); margin-bottom: 4px;">
                    <?= $verifiedTickets ?>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <?php if ($pendingTickets > 0): ?>
                    <span style="color: #b45309; font-weight: 700;">⚠️ <?= $pendingTickets ?> review needed</span>
                <?php else: ?>
                    <span><?= $activeTicketListings ?> active on feed</span>
                <?php endif; ?>
                <a href="tickets.php" style="color: var(--brand-coral); font-weight: 600; text-decoration: none; font-size: 0.78rem;">Verify &rarr;</a>
            </div>
        </div>

        <!-- Card 5: User Community -->
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); text-align: left; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Total Accounts</span>
                    <span style="font-size: 1.15rem;">👥</span>
                </div>
                <div class="kpi-value" style="font-size: 1.85rem; font-weight: 800; color: var(--brand-navy); margin-bottom: 4px;">
                    <?= $totalUsers ?>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span><?= $buyerCount ?> buyers · <?= $sellerCount ?> sellers</span>
                <a href="users.php" style="color: var(--brand-coral); font-weight: 600; text-decoration: none; font-size: 0.78rem;">Manage &rarr;</a>
            </div>
        </div>

        <!-- Card 6: Moderation Queue -->
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid <?= $openReports > 0 ? '#fca5a5' : 'var(--border-subtle)' ?>; border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); text-align: left; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.8rem; color: <?= $openReports > 0 ? '#b91c1c' : 'var(--text-muted)' ?>; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Dispute Reports</span>
                    <span style="font-size: 1.15rem;">🚩</span>
                </div>
                <div class="kpi-value" style="font-size: 1.85rem; font-weight: 800; color: <?= $openReports > 0 ? '#dc2626' : 'var(--brand-emerald-dark)' ?>; margin-bottom: 4px;">
                    <?= $openReports ?>
                </div>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; border-top: 1px solid var(--border-subtle); padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span><?= $openReports > 0 ? 'Requires attention' : 'All clear 🎉' ?></span>
                <a href="reports.php" style="color: var(--brand-coral); font-weight: 600; text-decoration: none; font-size: 0.78rem;">Triage &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Quick Action Hub -->
    <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 32px; box-shadow: var(--shadow-sm);">
        <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 14px;">
            ⚡ Administration Quick Actions
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
            <a href="users.php" class="dropdown-link" style="padding: 12px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-decoration: none; display: flex; align-items: center; gap: 10px; background: #f8fafc; transition: all 0.2s;">
                <span style="font-size: 1.25rem;">👥</span>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy);">User Directory</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $totalUsers ?> accounts</div>
                </div>
            </a>

            <a href="sellers.php" class="dropdown-link" style="padding: 12px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-decoration: none; display: flex; align-items: center; gap: 10px; background: #f8fafc; transition: all 0.2s;">
                <span style="font-size: 1.25rem;">🛡️</span>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy);">Seller KYC</div>
                    <div style="font-size: 0.75rem; color: <?= $pendingSellers > 0 ? '#b45309; font-weight:700;' : 'var(--text-muted);' ?>">
                        <?= $pendingSellers > 0 ? "$pendingSellers pending review" : "All verified" ?>
                    </div>
                </div>
            </a>

            <a href="tickets.php" class="dropdown-link" style="padding: 12px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-decoration: none; display: flex; align-items: center; gap: 10px; background: #f8fafc; transition: all 0.2s;">
                <span style="font-size: 1.25rem;">🎫</span>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy);">Ticket Verification</div>
                    <div style="font-size: 0.75rem; color: <?= $pendingTickets > 0 ? '#b45309; font-weight:700;' : 'var(--text-muted);' ?>">
                        <?= $pendingTickets > 0 ? "$pendingTickets awaiting check" : "$verifiedTickets verified" ?>
                    </div>
                </div>
            </a>

            <a href="orders.php" class="dropdown-link" style="padding: 12px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-decoration: none; display: flex; align-items: center; gap: 10px; background: #f8fafc; transition: all 0.2s;">
                <span style="font-size: 1.25rem;">📋</span>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy);">Platform Orders</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $totalOrdersCount ?> lifetime orders</div>
                </div>
            </a>

            <a href="reports.php" class="dropdown-link" style="padding: 12px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-decoration: none; display: flex; align-items: center; gap: 10px; background: #f8fafc; transition: all 0.2s;">
                <span style="font-size: 1.25rem;">🚩</span>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy);">Moderation Triage</div>
                    <div style="font-size: 0.75rem; color: <?= $openReports > 0 ? '#b91c1c; font-weight:700;' : 'var(--text-muted);' ?>">
                        <?= $openReports > 0 ? "$openReports open tickets" : "Clean queue" ?>
                    </div>
                </div>
            </a>

            <a href="seed_demo.php" class="dropdown-link" style="padding: 12px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-decoration: none; display: flex; align-items: center; gap: 10px; background: #f8fafc; transition: all 0.2s;">
                <span style="font-size: 1.25rem;">🛠️</span>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-navy);">Data Studio</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Reset &amp; seed demo items</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Live Activity Feeds (Two Columns on Desktop, Single Column on Mobile) -->
    <div class="admin-split-grid">
        <!-- Left: Recent Platform Orders -->
        <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column;">
            <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: 700; font-size: 1rem; color: var(--brand-navy); display: flex; align-items: center; gap: 8px;">
                    <span>📦</span> Recent Transactions
                </div>
                <a href="orders.php" style="color: var(--brand-coral); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                    View All (<?= $totalOrdersCount ?>) &rarr;
                </a>
            </div>

            <div style="flex: 1; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <?php if (empty($recentOrders)): ?>
                    <div style="padding: 36px 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                        No transactions recorded yet.
                    </div>
                <?php else: ?>
                    <table class="admin-table" style="margin: 0; border: none; border-radius: 0;">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Buyer &amp; Vendor</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="orders.php" style="font-weight: 700; color: var(--brand-navy); text-decoration: none;">
                                            #<?= (int) $order["order_id"] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; font-size: 0.88rem; color: var(--text-primary);"><?= e($order["buyer_name"]) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($order["seller_name"] ?? "Direct Merchant") ?></div>
                                    </td>
                                    <td style="font-weight: 700; color: var(--brand-navy);">
                                        ৳<?= number_format((float) $order["total_amount"], 2) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $payClass = match ($order["payment_status"]) {
                                            "paid" => "background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;",
                                            "pending" => "background: #fffbeb; color: #b45309; border: 1px solid #fde68a;",
                                            default => "background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3;"
                                        };
                                        ?>
                                        <span class="user-badge" style="<?= $payClass ?> font-size: 11px; padding: 2px 7px;">
                                            <?= e(ucfirst($order["payment_status"])) ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                        <?= date("M j, g:ia", strtotime($order["created_at"])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Urgent Moderation Reports -->
        <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column;">
            <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: 700; font-size: 1rem; color: var(--brand-navy); display: flex; align-items: center; gap: 8px;">
                    <span>🚩</span> Moderation Triage
                </div>
                <a href="reports.php" style="color: var(--brand-coral); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                    All Reports (<?= $totalReports ?>) &rarr;
                </a>
            </div>

            <div style="flex: 1; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <?php if (empty($recentReports)): ?>
                    <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 2rem; margin-bottom: 8px;">🎉</div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--brand-navy); margin-bottom: 4px;">Zero Open Disputes</div>
                        <div style="font-size: 0.82rem;">The marketplace moderation queue is completely clear.</div>
                    </div>
                <?php else: ?>
                    <table class="admin-table" style="margin: 0; border: none; border-radius: 0;">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReports as $rep): ?>
                                <tr>
                                    <td>
                                        <?php if ($rep["listing_title"]): ?>
                                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--brand-navy); max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($rep["listing_title"]) ?>">
                                                📦 <?= e($rep["listing_title"]) ?>
                                            </div>
                                        <?php elseif ($rep["reported_user_name"]): ?>
                                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--brand-navy);">
                                                👤 <?= e($rep["reported_user_name"]) ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);">General Concern</div>
                                        <?php endif; ?>
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">
                                            by <?= e($rep["reporter_name"]) ?> &bull; <?= date("M j", strtotime($rep["created_at"])) ?>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.82rem; color: var(--text-secondary); max-width: 180px;">
                                        <?= e($rep["report_reason"]) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusStyle = match ($rep["report_status"]) {
                                            "open" => "background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;",
                                            "reviewing" => "background: #fffbeb; color: #b45309; border: 1px solid #fde68a;",
                                            "resolved" => "background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;",
                                            default => "background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;"
                                        };
                                        ?>
                                        <span class="user-badge" style="<?= $statusStyle ?> font-size: 11px; padding: 2px 7px;">
                                            <?= e(ucfirst($rep["report_status"])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="reports.php" class="btn-demo-action studio-btn" style="padding: 3px 8px; font-size: 11px; text-decoration: none; display: inline-block;">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
