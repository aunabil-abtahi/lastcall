<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$listingId = filter_input(INPUT_GET, "listing_id", FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, "listing_id", FILTER_VALIDATE_INT);
if (!$listingId) {
    header("Location: index.php");
    exit;
}

$listingStmt = $pdo->prepare("SELECT listing_id, seller_id, title FROM listings WHERE listing_id = ? LIMIT 1");
$listingStmt->execute([$listingId]);
$listing = $listingStmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $reason = trim($_POST["report_reason"] ?? "");

    if (mb_strlen($reason) < 5) {
        $error = "Please describe the issue in at least 5 characters.";
    } elseif (mb_strlen($reason) > 255) {
        $error = "Reason cannot exceed 255 characters.";
    } elseif ((int) $listing["seller_id"] === (int) $_SESSION["user_id"]) {
        $error = "You cannot report your own listing.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, reported_user_id, listing_id, report_reason) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION["user_id"], $listing["seller_id"], $listingId, $reason]);
        header("Location: listing.php?id=" . $listingId . "&reported=1");
        exit;
    }
}

if (!function_exists("e")) {
    function e(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Report Listing | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page" style="padding: 40px 20px 80px;">
    <section class="form-card" style="max-width: 540px; margin: 0 auto; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <h2 style="font-size: 1.5rem; color: var(--brand-navy); margin-top: 0; margin-bottom: 6px;">
            🚩 Report Listing
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">
            Reporting: <strong><?= e($listing["title"]) ?></strong>
        </p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error" style="margin-bottom: 18px;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="report.php">
            <?= csrf_field() ?>
            <input type="hidden" name="listing_id" value="<?= (int) $listingId ?>">

            <div class="form-group" style="margin-bottom: 22px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px;">
                    Why are you reporting this listing? *
                </label>
                <textarea name="report_reason" rows="4" required maxlength="255" placeholder="Describe any inaccuracies, expired items, prohibited content, or fraud..." style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); font-family: inherit; resize: vertical;"></textarea>
                <small style="color: var(--text-muted); font-size: 12px;">Maximum 255 characters. Our moderation team reviews all reports.</small>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button class="primary-button" type="submit" style="padding: 12px 24px; font-weight: 700; cursor: pointer;">
                    Submit Report
                </button>
                <a href="listing.php?id=<?= (int) $listingId ?>" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none;">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
