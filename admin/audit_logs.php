<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "admin") {
    header("Location: ../index.php");
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
$totalLogs = (int) $stmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

$stmt = $pdo->prepare("
    SELECT log_id, table_name, record_id, action, old_data, new_data, changed_at 
    FROM audit_logs 
    ORDER BY changed_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Audit Logs | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 1.8rem; color: var(--brand-navy); margin-bottom: 8px;">System Audit Logs</h1>
            <p style="color: var(--text-secondary); margin: 0;">Automated DBMS tracking of critical database modifications via MySQL Triggers.</p>
        </div>
        <a href="dashboard.php" class="secondary-button" style="text-decoration: none;">&larr; Back to Dashboard</a>
    </div>

    <div style="background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>Action</th>
                        <th>Changes</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No audit logs found yet. Triggers will record changes automatically.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-muted);">#<?= $log['log_id'] ?></td>
                                <td><span class="badge"><?= e($log['table_name']) ?></span></td>
                                <td style="font-weight: bold;"><?= $log['record_id'] ?></td>
                                <td>
                                    <?php
                                    $actionColor = match($log['action']) {
                                        'INSERT' => '#047857',
                                        'UPDATE' => '#b45309',
                                        'DELETE' => '#be123c',
                                        default => 'black'
                                    };
                                    ?>
                                    <strong style="color: <?= $actionColor ?>;"><?= e($log['action']) ?></strong>
                                </td>
                                <td style="max-width: 350px;">
                                    <?php if ($log['old_data']): ?>
                                        <div style="font-size: 0.75rem; color: #dc2626; margin-bottom: 4px;">
                                            <strong style="color: #991b1b;">OLD:</strong> <?= e($log['old_data']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($log['new_data']): ?>
                                        <div style="font-size: 0.75rem; color: #059669;">
                                            <strong style="color: #065f46;">NEW:</strong> <?= e($log['new_data']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                    <?= date('M j, Y g:i A', strtotime($log['changed_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">Showing page <?= $page ?> of <?= $totalPages ?></span>
                <div style="display: flex; gap: 8px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="secondary-button" style="padding: 6px 12px; font-size: 0.85rem; text-decoration: none;">&larr; Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="secondary-button" style="padding: 6px 12px; font-size: 0.85rem; text-decoration: none;">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
