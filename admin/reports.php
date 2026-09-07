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

// Handle Moderation Actions (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $reportId = filter_input(INPUT_POST, "report_id", FILTER_VALIDATE_INT);
    $action = trim($_POST["action"] ?? "update_status");

    if (!$reportId) {
        $_SESSION["admin_message"] = "Invalid report ID.";
        $_SESSION["admin_message_type"] = "error";
        header("Location: reports.php");
        exit;
    }

    // 1. Basic Status Change
    if ($action === "update_status") {
        $status = trim($_POST["report_status"] ?? "");
        if (in_array($status, ["open", "reviewing", "resolved", "dismissed"], true)) {
            $stmt = $pdo->prepare("
                UPDATE reports 
                SET report_status = ?, reviewed_by = ?, reviewed_at = NOW() 
                WHERE report_id = ?
            ");
            $stmt->execute([$status, $adminId, $reportId]);
            $_SESSION["admin_message"] = "Report #$reportId status updated to " . ucfirst($status) . ".";
            $_SESSION["admin_message_type"] = "success";
        }
    }

    // 2. Takedown Reported Listing & Resolve Report
    elseif ($action === "takedown_listing") {
        $listingId = filter_input(INPUT_POST, "listing_id", FILTER_VALIDATE_INT);
        if ($listingId) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE listings SET listing_status = 'removed' WHERE listing_id = ?")
                    ->execute([$listingId]);
                $pdo->prepare("
                    UPDATE reports 
                    SET report_status = 'resolved', reviewed_by = ?, reviewed_at = NOW() 
                    WHERE report_id = ?
                ")->execute([$adminId, $reportId]);
                $pdo->commit();

                $_SESSION["admin_message"] = "Listing #$listingId has been taken down from marketplace and Report #$reportId resolved.";
                $_SESSION["admin_message_type"] = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION["admin_message"] = "Failed to takedown listing: " . $e->getMessage();
                $_SESSION["admin_message_type"] = "error";
            }
        }
    }

    // 3. Suspend Reported User & Resolve Report
    elseif ($action === "suspend_user") {
        $userId = filter_input(INPUT_POST, "reported_user_id", FILTER_VALIDATE_INT);
        if ($userId) {
            if ($userId === $adminId) {
                $_SESSION["admin_message"] = "Safety restriction: You cannot suspend your own administrator account.";
                $_SESSION["admin_message_type"] = "error";
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = ?")
                        ->execute([$userId]);
                    $pdo->prepare("
                        UPDATE reports 
                        SET report_status = 'resolved', reviewed_by = ?, reviewed_at = NOW() 
                        WHERE report_id = ?
                    ")->execute([$adminId, $reportId]);
                    $pdo->commit();

                    $_SESSION["admin_message"] = "Reported user #$userId has been suspended and Report #$reportId resolved.";
                    $_SESSION["admin_message_type"] = "success";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION["admin_message"] = "Failed to suspend user: " . $e->getMessage();
                    $_SESSION["admin_message_type"] = "error";
                }
            }
        }
    }

    // 4. Enforce Both (Takedown Listing AND Suspend User)
    elseif ($action === "enforce_both") {
        $listingId = filter_input(INPUT_POST, "listing_id", FILTER_VALIDATE_INT);
        $userId = filter_input(INPUT_POST, "reported_user_id", FILTER_VALIDATE_INT);

        if ($userId === $adminId) {
            $_SESSION["admin_message"] = "Safety restriction: Cannot suspend own administrator account.";
            $_SESSION["admin_message_type"] = "error";
        } else {
            $pdo->beginTransaction();
            try {
                if ($listingId) {
                    $pdo->prepare("UPDATE listings SET listing_status = 'removed' WHERE listing_id = ?")
                        ->execute([$listingId]);
                }
                if ($userId) {
                    $pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = ?")
                        ->execute([$userId]);
                }
                $pdo->prepare("
                    UPDATE reports 
                    SET report_status = 'resolved', reviewed_by = ?, reviewed_at = NOW() 
                    WHERE report_id = ?
                ")->execute([$adminId, $reportId]);
                $pdo->commit();

                $_SESSION["admin_message"] = "Listing removed, user suspended, and Report #$reportId marked as resolved.";
                $_SESSION["admin_message_type"] = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION["admin_message"] = "Moderation enforcement failed: " . $e->getMessage();
                $_SESSION["admin_message_type"] = "error";
            }
        }
    }

    header("Location: reports.php");
    exit;
}

// Filter by Status Tab
$statusFilter = trim($_GET["status"] ?? "");
$whereClause = "";
$params = [];

if (in_array($statusFilter, ["open", "reviewing", "resolved", "dismissed"], true)) {
    $whereClause = "WHERE r.report_status = ?";
    $params[] = $statusFilter;
}

// Fetch Reports with context
$reportsQuery = $pdo->prepare("
    SELECT 
        r.report_id,
        r.report_reason,
        r.report_status,
        r.created_at,
        r.reviewed_at,
        r.listing_id,
        r.reported_user_id,
        r.reporter_id,
        l.title AS listing_title,
        l.listing_status,
        l.original_price,
        l.listing_type,
        reporter.full_name AS reporter_name,
        reporter.email AS reporter_email,
        reported.full_name AS reported_name,
        reported.email AS reported_email,
        reported.role AS reported_role,
        reported.account_status AS reported_account_status,
        reviewer.full_name AS reviewer_name
    FROM reports r 
    LEFT JOIN listings l ON l.listing_id = r.listing_id 
    JOIN users reporter ON reporter.user_id = r.reporter_id 
    LEFT JOIN users reported ON reported.user_id = r.reported_user_id 
    LEFT JOIN users reviewer ON reviewer.user_id = r.reviewed_by
    $whereClause
    ORDER BY FIELD(r.report_status, 'open', 'reviewing', 'resolved', 'dismissed'), r.created_at DESC
");
$reportsQuery->execute($params);
$reports = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);

// Counts by status for badge tabs
$countsQuery = $pdo->query("
    SELECT 
        COUNT(*) AS total_reports,
        SUM(CASE WHEN report_status = 'open' THEN 1 ELSE 0 END) AS count_open,
        SUM(CASE WHEN report_status = 'reviewing' THEN 1 ELSE 0 END) AS count_reviewing,
        SUM(CASE WHEN report_status = 'resolved' THEN 1 ELSE 0 END) AS count_resolved,
        SUM(CASE WHEN report_status = 'dismissed' THEN 1 ELSE 0 END) AS count_dismissed
    FROM reports
");
$counts = $countsQuery->fetch(PDO::FETCH_ASSOC);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Moderation Reports & Disputes | LastCall Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2>🚩 Moderation &amp; Dispute Reports</h2>
                <p>Investigate marketplace safety incidents, take down fraudulent listings, and manage account restrictions.</p>
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

    <!-- Filter Tabs with Counts -->
    <div style="display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap;">
        <a href="reports.php" class="btn-demo-action <?= $statusFilter === '' ? 'seed-btn' : 'studio-btn' ?>" style="text-decoration: none;">
            All Reports (<?= (int)$counts["total_reports"] ?>)
        </a>
        <a href="reports.php?status=open" class="btn-demo-action <?= $statusFilter === 'open' ? 'seed-btn' : 'studio-btn' ?>" style="text-decoration: none;">
            🔴 Open (<?= (int)$counts["count_open"] ?>)
        </a>
        <a href="reports.php?status=reviewing" class="btn-demo-action <?= $statusFilter === 'reviewing' ? 'seed-btn' : 'studio-btn' ?>" style="text-decoration: none;">
            🟡 Reviewing (<?= (int)$counts["count_reviewing"] ?>)
        </a>
        <a href="reports.php?status=resolved" class="btn-demo-action <?= $statusFilter === 'resolved' ? 'seed-btn' : 'studio-btn' ?>" style="text-decoration: none;">
            🟢 Resolved (<?= (int)$counts["count_resolved"] ?>)
        </a>
        <a href="reports.php?status=dismissed" class="btn-demo-action <?= $statusFilter === 'dismissed' ? 'seed-btn' : 'studio-btn' ?>" style="text-decoration: none;">
            ⚪ Dismissed (<?= (int)$counts["count_dismissed"] ?>)
        </a>
    </div>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Report #</th>
                    <th>Reported Listing / Deal</th>
                    <th>Reported Party</th>
                    <th>Reporter</th>
                    <th>Reason / Details</th>
                    <th>Status</th>
                    <th>Enforcement &amp; Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No moderation reports found under this category.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>
                                <strong>#<?= (int) $r["report_id"] ?></strong>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                    <?= date("d M Y", strtotime($r["created_at"])) ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($r["listing_id"])): ?>
                                    <div style="font-weight: 700; color: var(--text-primary);">
                                        <a href="../listing.php?id=<?= (int) $r["listing_id"] ?>" target="_blank" style="color: var(--brand-navy); text-decoration: underline;">
                                            <?= e($r["listing_title"] ?? "Listing #" . $r["listing_id"]) ?>
                                        </a>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center; margin-top: 4px;">
                                        <span class="badge <?= ($r["listing_type"] ?? "") === "food" ? "" : "ticket" ?>" style="font-size: 10px; padding: 2px 6px; margin: 0;">
                                            <?= ucfirst($r["listing_type"] ?? "Item") ?>
                                        </span>
                                        <span class="user-badge <?= e($r["listing_status"] ?? "") ?>" style="font-size: 10px; padding: 2px 6px;">
                                            <?= ucfirst($r["listing_status"] ?? "Unknown") ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 13px;">— (General User Dispute)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r["reported_name"])): ?>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= e($r["reported_name"]) ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">
                                        Role: <?= ucfirst($r["reported_role"] ?? "User") ?>
                                    </div>
                                    <span class="user-badge <?= e($r["reported_account_status"] ?? "active") ?>" style="font-size: 10px; padding: 2px 6px; margin-top: 2px; display: inline-block;">
                                        Status: <?= ucfirst($r["reported_account_status"] ?? "active") ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600; color: var(--text-primary);">
                                    <?= e($r["reporter_name"]) ?>
                                </div>
                                <div style="font-size: 11.5px; color: var(--text-muted);">
                                    <?= e($r["reporter_email"]) ?>
                                </div>
                            </td>
                            <td style="max-width: 260px;">
                                <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.45; background: var(--surface-muted); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-subtle);">
                                    "<?= e($r["report_reason"]) ?>"
                                </div>
                                <?php if (!empty($r["reviewer_name"])): ?>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                        Reviewed by <?= e($r["reviewer_name"]) ?> on <?= date("d M, h:i A", strtotime($r["reviewed_at"])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="user-badge <?= e($r["report_status"]) ?>" style="<?= $r['report_status'] === 'open' ? 'background:#fee2e2; color:#b91c1c;' : ($r['report_status'] === 'reviewing' ? 'background:#fef3c7; color:#b45309;' : ($r['report_status'] === 'resolved' ? 'background:#ecfdf5; color:#065f46;' : '')) ?>">
                                    <?= ucfirst($r["report_status"]) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <!-- Status Update Form -->
                                    <form method="POST" action="reports.php" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="report_id" value="<?= (int) $r["report_id"] ?>">
                                        <select name="report_status" style="padding: 3px 6px; font-size: 11.5px; border-radius: 4px; border: 1px solid var(--border-medium);">
                                            <option value="open" <?= $r["report_status"] === "open" ? "selected" : "" ?>>Open</option>
                                            <option value="reviewing" <?= $r["report_status"] === "reviewing" ? "selected" : "" ?>>Reviewing</option>
                                            <option value="resolved" <?= $r["report_status"] === "resolved" ? "selected" : "" ?>>Resolved</option>
                                            <option value="dismissed" <?= $r["report_status"] === "dismissed" ? "selected" : "" ?>>Dismissed</option>
                                        </select>
                                        <button type="submit" class="dashboard-button" style="padding: 3px 8px; font-size: 11.5px; line-height: 1.3; cursor: pointer; border: none;">
                                            Set
                                        </button>
                                    </form>

                                    <!-- Quick Moderation Enforcement Actions -->
                                    <?php if (!empty($r["listing_id"]) && ($r["listing_status"] ?? "") !== "removed"): ?>
                                        <form method="POST" action="reports.php" onsubmit="return confirm('Immediately remove this listing from marketplace?');" style="margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="takedown_listing">
                                            <input type="hidden" name="report_id" value="<?= (int) $r["report_id"] ?>">
                                            <input type="hidden" name="listing_id" value="<?= (int) $r["listing_id"] ?>">
                                            <button type="submit" class="btn-demo-action clear-btn" style="padding: 3px 8px; font-size: 11px; width: 100%; justify-content: center;">
                                                🗑️ Takedown Listing
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!empty($r["reported_user_id"]) && ($r["reported_account_status"] ?? "") === "active" && (int)$r["reported_user_id"] !== $adminId): ?>
                                        <form method="POST" action="reports.php" onsubmit="return confirm('Suspend this user account immediately?');" style="margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="suspend_user">
                                            <input type="hidden" name="report_id" value="<?= (int) $r["report_id"] ?>">
                                            <input type="hidden" name="reported_user_id" value="<?= (int) $r["reported_user_id"] ?>">
                                            <button type="submit" class="btn-demo-action clear-btn" style="padding: 3px 8px; font-size: 11px; width: 100%; justify-content: center; background: #fff1f2; color: #e11d48; border-color: #fecdd3;">
                                                ⛔ Suspend User
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
