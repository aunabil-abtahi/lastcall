<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "admin") {
    header("Location: ../index.php");
    exit;
}

$adminId = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $sellerProfileId = (int) ($_POST["seller_profile_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if (
        $sellerProfileId > 0 &&
        in_array($action, ["approve", "reject"], true)
    ) {
        try {
            $pdo->beginTransaction();

            $profileQuery = $pdo->prepare("
                SELECT user_id
                FROM seller_profiles
                WHERE seller_profile_id = ?
                  AND verification_status = 'pending'
                LIMIT 1
            ");

            $profileQuery->execute([$sellerProfileId]);
            $sellerProfile = $profileQuery->fetch(PDO::FETCH_ASSOC);

            if ($sellerProfile) {
                if ($action === "approve") {
                    $approveProfile = $pdo->prepare("
                        UPDATE seller_profiles
                        SET verification_status = 'approved',
                            rejection_reason = NULL,
                            verified_by = ?,
                            verified_at = NOW()
                        WHERE seller_profile_id = ?
                    ");

                    $approveProfile->execute([$adminId, $sellerProfileId]);

                    $makeSeller = $pdo->prepare("
                        UPDATE users
                        SET role = 'seller'
                        WHERE user_id = ?
                    ");

                    $makeSeller->execute([$sellerProfile["user_id"]]);

                    $_SESSION["admin_message"] =
                        "Seller application approved successfully.";
                } else {
                    $rejectionReason = trim($_POST["rejection_reason"] ?? "");
                    if ($rejectionReason === "") {
                        $rejectionReason = "Application does not meet current verification requirements.";
                    }

                    $rejectProfile = $pdo->prepare("
                        UPDATE seller_profiles
                        SET verification_status = 'rejected',
                            rejection_reason = ?,
                            verified_by = ?,
                            verified_at = NOW()
                        WHERE seller_profile_id = ?
                    ");

                    $rejectProfile->execute([$rejectionReason, $adminId, $sellerProfileId]);

                    $_SESSION["admin_message"] =
                        "Seller application rejected.";
                }

                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION["admin_message"] =
                "Could not update the seller application.";
        }
    }

    header("Location: sellers.php");
    exit;
}

$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);

$sellersQuery = $pdo->query("
    SELECT
        sp.seller_profile_id,
        u.full_name,
        u.email,
        u.phone,
        sp.seller_type,
        sp.business_name,
        sp.verification_document,
        sp.verification_status,
        sp.rejection_reason,
        sp.created_at
    FROM seller_profiles sp
    JOIN users u ON sp.user_id = u.user_id
    ORDER BY
        CASE sp.verification_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            ELSE 3
        END,
        sp.created_at DESC
");

$sellers = $sellersQuery->fetchAll(PDO::FETCH_ASSOC);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function sellerTypeLabel(string $type): string
{
    $labels = [
        "food_business" => "Food Business",
        "event_organizer" => "Event Organizer",
        "individual_ticket_seller" => "Individual Ticket Seller"
    ];

    return $labels[$type] ?? $type;
}
$pageTitle = "Seller Verification | LastCall Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>Seller Verification</h2>
        <p>Review and approve seller applications.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Seller</th>
                    <th>Type</th>
                    <th>Business</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td>
                            <strong><?= e($seller["full_name"]) ?></strong>
                            <br>
                            <span><?= e($seller["email"]) ?></span>
                        </td>

                        <td><?= e(sellerTypeLabel($seller["seller_type"])) ?></td>

                        <td><?= e($seller["business_name"] ?? "-") ?></td>

                        <td>
                            <?php if (!empty($seller["verification_document"])): ?>
                                <?php $docUrl = '../' . ltrim($seller["verification_document"], '/'); ?>
                                <a href="<?= e($docUrl) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; color: #3b82f6; text-decoration: none; font-size: 0.85rem; padding: 4px 8px; background: #eff6ff; border-radius: 4px; border: 1px solid #bfdbfe; transition: background 0.2s;" onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                     View Document
                                </a>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">No document</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="status <?= e($seller["verification_status"]) ?>">
                                <?= e(ucfirst($seller["verification_status"])) ?>
                            </span>
                            <?php if ($seller["verification_status"] === "rejected" && !empty($seller["rejection_reason"])): ?>
                                <div style="font-size:0.75rem; color:#b91c1c; margin-top:5px; line-height:1.3; max-width:180px;">
                                    <strong>Reason:</strong> <?= e($seller["rejection_reason"]) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($seller["verification_status"] === "pending"): ?>
                                <form method="POST" class="action-form" style="display:flex; flex-direction:column; gap:8px; width: 100%;">
                                    <?= csrf_field() ?>
                                    <input
                                        type="hidden"
                                        name="seller_profile_id"
                                        value="<?= (int) $seller["seller_profile_id"] ?>">

                                    <div style="position: relative;">
                                        
                                        <input 
                                            type="text" 
                                            name="rejection_reason" 
                                            placeholder="Reason if rejecting..." 
                                            style="font-size:0.8rem; padding:6px 8px 6px 28px; border:1px solid #e2e8f0; border-radius:6px; width:100%; min-width:140px; outline: none; transition: border-color 0.2s;"
                                            onfocus="this.style.borderColor='#3b82f6'"
                                            onblur="this.style.borderColor='#e2e8f0'"
                                        >
                                    </div>

                                    <div style="display:flex; gap:6px;">
                                        <button
                                            type="submit"
                                            name="action"
                                            value="approve"
                                            class="approve-button"
                                            style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; padding: 6px 10px; background-color: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 500; transition: background-color 0.2s;"
                                            onmouseover="this.style.backgroundColor='#059669'"
                                            onmouseout="this.style.backgroundColor='#10b981'"
                                        >
                                             Approve
                                        </button>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="reject"
                                            class="reject-button"
                                            style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; padding: 6px 10px; background-color: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 500; transition: background-color 0.2s;"
                                            onclick="return confirmReject(this.form)"
                                            onmouseover="this.style.backgroundColor='#dc2626'"
                                            onmouseout="this.style.backgroundColor='#ef4444'"
                                        >
                                             Reject
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
function confirmReject(form) {
    const reasonInput = form.querySelector('input[name="rejection_reason"]');
    if (!reasonInput.value.trim()) {
        const promptReason = prompt("Please enter a rejection reason (or leave blank to use default):", "");
        if (promptReason === null) return false;
        if (promptReason.trim()) {
            reasonInput.value = promptReason.trim();
        }
    }
    return confirm("Confirm rejection of this seller application?");
}
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>