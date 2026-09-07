<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "admin") {
    header("Location: ../index.php");
    exit;
}

$adminId = (int) $_SESSION["user_id"];
$flashMessage = $_SESSION["admin_message"] ?? "";
$flashType = $_SESSION["admin_message_type"] ?? "success";
unset($_SESSION["admin_message"], $_SESSION["admin_message_type"]);

// Handle Status or Role Modifications
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $action = trim($_POST["action"] ?? "");
    $targetUserId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);

    if (!$targetUserId) {
        $_SESSION["admin_message"] = "Invalid user ID provided.";
        $_SESSION["admin_message_type"] = "error";
        header("Location: users.php");
        exit;
    }

    if ($action === "change_status") {
        $newStatus = trim($_POST["status"] ?? "");
        if (!in_array($newStatus, ["active", "suspended", "blocked"], true)) {
            $_SESSION["admin_message"] = "Invalid account status.";
            $_SESSION["admin_message_type"] = "error";
            header("Location: users.php");
            exit;
        }

        // Prevent admin from suspending their own active account
        if ($targetUserId === $adminId && $newStatus !== "active") {
            $_SESSION["admin_message"] = "Safety restriction: You cannot suspend or block your own administrator account.";
            $_SESSION["admin_message_type"] = "error";
            header("Location: users.php");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE user_id = ?");
        $stmt->execute([$newStatus, $targetUserId]);

        $_SESSION["admin_message"] = "User #$targetUserId account status changed to " . ucfirst($newStatus) . ".";
        $_SESSION["admin_message_type"] = "success";
        header("Location: users.php");
        exit;
    }

    if ($action === "change_role") {
        $newRole = trim($_POST["role"] ?? "");
        if (!in_array($newRole, ["buyer", "seller", "admin"], true)) {
            $_SESSION["admin_message"] = "Invalid role specified.";
            $_SESSION["admin_message_type"] = "error";
            header("Location: users.php");
            exit;
        }

        // Prevent admin from removing their own admin privileges
        if ($targetUserId === $adminId && $newRole !== "admin") {
            $_SESSION["admin_message"] = "Safety restriction: You cannot revoke your own administrator role.";
            $_SESSION["admin_message_type"] = "error";
            header("Location: users.php");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->execute([$newRole, $targetUserId]);

        $_SESSION["admin_message"] = "User #$targetUserId role changed to " . ucfirst($newRole) . ".";
        $_SESSION["admin_message_type"] = "success";
        header("Location: users.php");
        exit;
    }
}

// Compute Summary Statistics
$statsQuery = $pdo->query("
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'buyer' THEN 1 ELSE 0 END) AS total_buyers,
        SUM(CASE WHEN role = 'seller' THEN 1 ELSE 0 END) AS total_sellers,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins,
        SUM(CASE WHEN account_status IN ('suspended', 'blocked') THEN 1 ELSE 0 END) AS total_suspended
    FROM users
");
$userStats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Search & Filter Parameters
$search = trim($_GET["q"] ?? "");
$roleFilter = trim($_GET["role"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$whereClauses = [];
$params = [];

if ($search !== "") {
    $whereClauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (in_array($roleFilter, ["buyer", "seller", "admin"], true)) {
    $whereClauses[] = "u.role = ?";
    $params[] = $roleFilter;
}

if (in_array($statusFilter, ["active", "suspended", "blocked"], true)) {
    $whereClauses[] = "u.account_status = ?";
    $params[] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$userListStmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.account_status,
        u.created_at,
        loc.city,
        loc.area,
        sp.business_name,
        sp.verification_status AS seller_status
    FROM users u
    LEFT JOIN locations loc ON loc.location_id = u.location_id
    LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
    $whereSql
    ORDER BY u.created_at DESC
");
$userListStmt->execute($params);
$users = $userListStmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "User Directory & Access Control | LastCall Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2>👥 User Directory &amp; Access Control</h2>
                <p>Manage registered buyers, merchants, and platform administrators.</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn-demo-action studio-btn">
                    &larr; Admin Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if ($flashMessage !== ""): ?>
        <div class="alert <?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>" style="margin-bottom: 24px;">
            <?= e($flashMessage) ?>
        </div>
    <?php endif; ?>

    <!-- User KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 28px;">
        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Total Users</div>
            <div style="font-size: 1.7rem; font-weight: 800; color: var(--brand-navy);"><?= (int) $userStats["total_users"] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">All registered accounts</div>
        </div>

        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">🛍️ Active Buyers</div>
            <div style="font-size: 1.7rem; font-weight: 800; color: var(--brand-emerald-dark);"><?= (int) $userStats["total_buyers"] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">Customers &amp; ticket buyers</div>
        </div>

        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">🏪 Vendors &amp; Sellers</div>
            <div style="font-size: 1.7rem; font-weight: 800; color: var(--brand-coral);"><?= (int) $userStats["total_sellers"] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">Food partners &amp; ticket sellers</div>
        </div>

        <div class="kpi-card" style="background: var(--surface-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">⚠️ Flagged / Suspended</div>
            <div style="font-size: 1.7rem; font-weight: 800; color: <?= (int)$userStats['total_suspended'] > 0 ? '#dc2626' : 'var(--text-muted)' ?>;"><?= (int) $userStats["total_suspended"] ?></div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">Access currently restricted</div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" action="users.php" class="filter-form" style="margin-bottom: 24px;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search user name, email, or phone number...">
        <select name="role">
            <option value="">All Roles</option>
            <option value="buyer" <?= $roleFilter === "buyer" ? "selected" : "" ?>>Buyers</option>
            <option value="seller" <?= $roleFilter === "seller" ? "selected" : "" ?>>Sellers / Vendors</option>
            <option value="admin" <?= $roleFilter === "admin" ? "selected" : "" ?>>Administrators</option>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <option value="active" <?= $statusFilter === "active" ? "selected" : "" ?>>Active</option>
            <option value="suspended" <?= $statusFilter === "suspended" ? "selected" : "" ?>>Suspended</option>
            <option value="blocked" <?= $statusFilter === "blocked" ? "selected" : "" ?>>Blocked</option>
        </select>
        <button type="submit">Filter Directory</button>
        <?php if ($search !== "" || $roleFilter !== "" || $statusFilter !== ""): ?>
            <a href="users.php">Clear Filters</a>
        <?php endif; ?>
    </form>

    <!-- Users Table -->
    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>User / Name</th>
                    <th>Contact Info</th>
                    <th>Location</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Moderation Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No users matched your search criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong>#<?= (int) $u["user_id"] ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="seller-avatar-circle" style="width: 34px; height: 34px; font-size: 13px;">
                                        <?= mb_strtoupper(mb_substr($u["full_name"], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-primary);">
                                            <?= e($u["full_name"]) ?>
                                            <?php if ((int)$u["user_id"] === $adminId): ?>
                                                <span style="font-size: 11px; color: var(--brand-navy); background: var(--brand-navy-tint); padding: 2px 6px; border-radius: 4px; margin-left: 4px;">You</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($u["business_name"])): ?>
                                            <div style="font-size: 11.5px; color: var(--text-muted);">
                                                🏪 <?= e($u["business_name"]) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 13px;"><?= e($u["email"]) ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?= e($u["phone"]) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($u["city"])): ?>
                                    <span style="font-size: 12.5px;">📍 <?= e($u["area"] ? $u["area"] . ", " : "") ?><?= e($u["city"]) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="user-badge <?= e($u["role"]) ?>">
                                    <?= e(ucfirst($u["role"])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="user-badge <?= e($u["account_status"]) ?>" style="<?= $u['account_status'] === 'suspended' ? 'background:#fef3c7; color:#b45309; border:1px solid #fde68a;' : ($u['account_status'] === 'blocked' ? 'background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;' : '') ?>">
                                    <?= e(ucfirst($u["account_status"])) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);">
                                    <?= date("d M Y", strtotime($u["created_at"])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int) $u["user_id"] === $adminId): ?>
                                    <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">Protected</span>
                                <?php else: ?>
                                    <form method="POST" action="users.php" style="display: inline-flex; gap: 6px; align-items: center; margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="user_id" value="<?= (int) $u["user_id"] ?>">
                                        <select name="status" style="padding: 4px 8px; font-size: 12px; border-radius: 6px; border: 1px solid var(--border-medium);">
                                            <option value="active" <?= $u["account_status"] === "active" ? "selected" : "" ?>>Active</option>
                                            <option value="suspended" <?= $u["account_status"] === "suspended" ? "selected" : "" ?>>Suspended</option>
                                            <option value="blocked" <?= $u["account_status"] === "blocked" ? "selected" : "" ?>>Blocked</option>
                                        </select>
                                        <button type="submit" class="dashboard-button" style="padding: 4px 10px; font-size: 12px; line-height: 1.4; border: none; cursor: pointer;">
                                            Update
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
