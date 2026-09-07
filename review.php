<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$orderId = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
if (!$orderId) {
    header("Location: my_orders.php");
    exit;
}

$orderStmt = $pdo->prepare("
    SELECT o.order_id, l.seller_id, oi.item_title 
    FROM orders o 
    JOIN order_items oi ON oi.order_id = o.order_id 
    JOIN listings l ON l.listing_id = oi.listing_id 
    WHERE o.order_id = ? 
      AND o.buyer_id = ? 
      AND o.order_status = 'completed' 
    LIMIT 1
");
$orderStmt->execute([$orderId, $_SESSION["user_id"]]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: my_orders.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $rating = filter_input(INPUT_POST, "rating", FILTER_VALIDATE_INT);
    $text = trim($_POST["review_text"] ?? "");

    if (!$rating || $rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } elseif (mb_strlen($text) > 2000) {
        $error = "Review text cannot exceed 2000 characters.";
    } else {
        try {
            $pdo->prepare("
                INSERT INTO reviews (order_id, buyer_id, seller_id, rating, review_text) 
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $orderId,
                $_SESSION["user_id"],
                $order["seller_id"],
                $rating,
                $text !== "" ? $text : null
            ]);
            header("Location: my_orders.php");
            exit;
        } catch (PDOException $e) {
            $error = "You have already reviewed this order.";
        }
    }
}

if (!function_exists("e")) {
    function e(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Write a Review | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page" style="padding: 40px 20px 80px;">
    <section class="form-card" style="max-width: 540px; margin: 0 auto; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <h2 style="font-size: 1.5rem; color: var(--brand-navy); margin-top: 0; margin-bottom: 6px;">
            Review <?= e($order["item_title"]) ?>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
            Share your feedback with the vendor and the LastCall community.
        </p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error" style="margin-bottom: 18px;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="review.php">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">

            <div class="form-group" style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px;">Rating (1 to 5 Stars) *</label>
                <select name="rating" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
                    <option value="">Choose rating</option>
                    <option value="5">⭐⭐⭐⭐⭐ 5 Stars (Exceptional)</option>
                    <option value="4">⭐⭐⭐⭐ 4 Stars (Great)</option>
                    <option value="3">⭐⭐⭐ 3 Stars (Average)</option>
                    <option value="2">⭐⭐ 2 Stars (Poor)</option>
                    <option value="1">⭐ 1 Star (Very Bad)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 22px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px;">Your Review (Optional)</label>
                <textarea name="review_text" rows="4" maxlength="2000" placeholder="How was the food quality or ticket experience?" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); font-family: inherit; resize: vertical;"></textarea>
                <small style="color: var(--text-muted); font-size: 12px;">Maximum 2,000 characters.</small>
            </div>

            <button class="primary-button" type="submit" style="width: 100%; padding: 12px; font-weight: 700; cursor: pointer;">
                Submit Review
            </button>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
