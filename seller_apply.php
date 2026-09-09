<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION["user_id"];
$errors = [];
$success = "";

$profileQuery = $pdo->prepare("
    SELECT seller_type, business_name, verification_document, verification_status, rejection_reason
    FROM seller_profiles
    WHERE user_id = ?
    LIMIT 1
");

$profileQuery->execute([$userId]);
$existingProfile = $profileQuery->fetch(PDO::FETCH_ASSOC);

$allowedTypes = [
    "food_business",
    "event_organizer",
    "individual_ticket_seller"
];

// Pre-fill form data if previously submitted or submitted via POST
$formData = [
    "seller_type" => $existingProfile["seller_type"] ?? "",
    "business_name" => $existingProfile["business_name"] ?? "",
    "verification_document" => $existingProfile["verification_document"] ?? ""
];

// Allow POST if new applicant or if rejected (resubmission)
$canSubmit = !$existingProfile || $existingProfile["verification_status"] === "rejected";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $canSubmit) {
    require_csrf();
    $formData["seller_type"] = $_POST["seller_type"] ?? "";
    $formData["business_name"] = trim($_POST["business_name"] ?? "");
    $formData["verification_document"] = trim($_POST["verification_document"] ?? "");

    if (!in_array($formData["seller_type"], $allowedTypes, true)) {
        $errors[] = "Please select a valid seller type.";
    }

    if ($formData["business_name"] === "") {
        $errors[] = "Business or seller name is required.";
    }

    if (count($errors) === 0) {
        if ($existingProfile) {
            // Update existing rejected profile and reset to pending
            $updateProfile = $pdo->prepare("
                UPDATE seller_profiles
                SET seller_type = ?,
                    business_name = ?,
                    verification_document = ?,
                    verification_status = 'pending',
                    rejection_reason = NULL,
                    verified_by = NULL,
                    verified_at = NULL,
                    updated_at = NOW()
                WHERE user_id = ?
            ");

            $updateProfile->execute([
                $formData["seller_type"],
                $formData["business_name"],
                $formData["verification_document"] ?: null,
                $userId
            ]);

            $success = "Your seller application has been updated and resubmitted for admin review.";
        } else {
            // Insert new profile
            $insertProfile = $pdo->prepare("
                INSERT INTO seller_profiles (
                    user_id,
                    seller_type,
                    business_name,
                    verification_document,
                    verification_status
                ) VALUES (?, ?, ?, ?, 'pending')
            ");

            $insertProfile->execute([
                $userId,
                $formData["seller_type"],
                $formData["business_name"],
                $formData["verification_document"] ?: null
            ]);

            $success = "Your seller application has been submitted for admin review.";
        }

        // Refresh profile state
        $profileQuery->execute([$userId]);
        $existingProfile = $profileQuery->fetch(PDO::FETCH_ASSOC);
    }
}

function sellerTypeLabel(string $type): string {
    $labels = [
        "food_business" => "Food Business",
        "event_organizer" => "Event Organizer",
        "individual_ticket_seller" => "Individual Ticket Seller"
    ];

    return $labels[$type] ?? $type;
}

$pageTitle = "Become a Verified Seller | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page">
    <section class="form-card" style="max-width: 580px;">
        <h1>Become a Verified Seller</h1>
        <p class="form-intro">
            Partner with LastCall to monetize surplus meals and verified event tickets.
        </p>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if (count($errors) > 0): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($existingProfile && $existingProfile["verification_status"] === "approved"): ?>
            <!-- State 1: Already Approved -->
            <div class="status-card" style="border-left: 4px solid #10b981; padding: 1.75rem; background: var(--bg-card); border-radius: 12px; text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🎉</div>
                <h2 style="font-size: 1.35rem; margin-bottom: 0.5rem;">You are a Verified Seller</h2>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Your account is approved as a verified <strong><?= e(sellerTypeLabel($existingProfile["seller_type"])) ?></strong> (<?= e($existingProfile["business_name"]) ?>).
                </p>
                <a href="seller/dashboard.php" class="primary-button" style="text-decoration:none; display:inline-block; padding: 0.75rem 1.5rem;">
                    Go to Seller Dashboard
                </a>
            </div>

        <?php elseif ($existingProfile && $existingProfile["verification_status"] === "pending"): ?>
            <!-- State 2: Under Review -->
            <div class="status-card" style="border-left: 4px solid #f59e0b; padding: 1.75rem; background: var(--bg-card); border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="font-size: 1.8rem;">⏳</div>
                    <div>
                        <h2 style="font-size: 1.25rem; margin: 0;">Application Under Review</h2>
                        <span class="user-badge pending" style="display: inline-block; margin-top: 4px;">Pending Verification</span>
                    </div>
                </div>

                <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0.75rem 0 1.25rem; line-height: 1.5;">
                    Your application for <strong><?= e($existingProfile["business_name"]) ?></strong> (<?= e(sellerTypeLabel($existingProfile["seller_type"])) ?>) is currently queued for administrator verification.
                </p>

                <div style="font-size: 0.85rem; background: rgba(245, 158, 11, 0.1); color: #b45309; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.2);">
                    🛡️ Verification typically completes within 24 hours. You will receive listing permissions immediately upon approval.
                </div>
            </div>

        <?php else: ?>
            <!-- State 3 & 4: New Application OR Rejected Resubmission -->
            <?php if ($existingProfile && $existingProfile["verification_status"] === "rejected"): ?>
                <div class="alert alert-error" style="margin-bottom: 1.75rem; border-left: 4px solid #ef4444; padding: 1.25rem; border-radius: 8px;">
                    <div style="font-weight: 700; font-size: 1rem; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 6px;">
                        <span>⚠️</span> Application Not Approved
                    </div>
                    <p style="font-size: 0.9rem; margin-bottom: 0.5rem; color: #7f1d1d;">
                        An administrator reviewed your previous application and left the following feedback:
                    </p>
                    <blockquote style="background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; padding: 0.65rem 0.9rem; border-radius: 4px; font-weight: 600; color: #991b1b; margin: 0.5rem 0 0.85rem; font-size: 0.92rem;">
                        <?= e($existingProfile["rejection_reason"] ?: "Application does not meet current verification requirements.") ?>
                    </blockquote>
                    <p style="font-size: 0.85rem; margin: 0; color: #7f1d1d;">
                        Please review the feedback, update your information or verification reference below, and resubmit.
                    </p>
                </div>
            <?php endif; ?>

            <form method="POST" action="seller_apply.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="seller_type">Seller Category *</label>
                    <select id="seller_type" name="seller_type" required>
                        <option value="">Select your business category</option>
                        <option value="food_business" <?= $formData["seller_type"] === "food_business" ? "selected" : "" ?>>
                            🍽️ Food Business (Restaurant, Bakery, Cafe)
                        </option>
                        <option value="event_organizer" <?= $formData["seller_type"] === "event_organizer" ? "selected" : "" ?>>
                            🎭 Event Organizer (Festivals, Concerts, Theaters)
                        </option>
                        <option value="individual_ticket_seller" <?= $formData["seller_type"] === "individual_ticket_seller" ? "selected" : "" ?>>
                            🎟️ Individual Ticket Reseller
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="business_name">Business or Seller Brand Name *</label>
                    <input
                        type="text"
                        id="business_name"
                        name="business_name"
                        value="<?= e($formData["business_name"]) ?>"
                        placeholder="e.g. Artisan Bakery &amp; Cafe"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="verification_document">
                        Verification Reference (Trade License, NID, or Org URL)
                    </label>
                    <input
                        type="text"
                        id="verification_document"
                        name="verification_document"
                        placeholder="e.g. Trade License #TRAD-2026-9921 or National ID"
                        value="<?= e($formData["verification_document"]) ?>"
                    >
                    <small style="color: var(--text-muted); font-size: 0.78rem; display: block; margin-top: 4px;">
                        Providing authentic verification credentials speeds up your account approval.
                    </small>
                </div>

                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1rem; margin-bottom: 0.5rem; text-align: center;">
                    By submitting this application, you agree to our <a href="terms.php" target="_blank" style="color: var(--brand-coral); text-decoration: underline;">Terms and Conditions</a>.
                </div>
                <button type="submit" class="primary-button" style="margin-top: 0.5rem;">
                    <?= ($existingProfile && $existingProfile["verification_status"] === "rejected") ? "Update &amp; Resubmit Application" : "Submit Application for Verification" ?>
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>