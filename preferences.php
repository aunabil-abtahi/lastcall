<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

requireLogin();

$uid = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $type = $_POST["preferred_listing_type"] ?? "";
    $city = trim($_POST["preferred_city"] ?? "");
    $area = trim($_POST["preferred_area"] ?? "");

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM user_preferences WHERE user_id = ?")->execute([$uid]);

    if (in_array($type, ["food", "ticket"], true) || $city !== "" || $area !== "") {
        $pdo->prepare("
            INSERT INTO user_preferences (user_id, preferred_listing_type, preferred_city, preferred_area)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $uid,
            in_array($type, ["food", "ticket"], true) ? $type : "food",
            $city ?: null,
            $area ?: null
        ]);
    }

    $pdo->commit();
    header("Location: recommendations.php");
    exit;
}

$q = $pdo->prepare("SELECT preferred_listing_type, preferred_city, preferred_area FROM user_preferences WHERE user_id = ? LIMIT 1");
$q->execute([$uid]);
$p = $q->fetch(PDO::FETCH_ASSOC) ?: [];

$pageTitle = "Preferences | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Your Preferences</h1>
        <p class="form-intro">Used to improve your personalized recommendations.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Preferred deal type</label>
                <select name="preferred_listing_type">
                    <option value="">No preference</option>
                    <option value="food" <?= ($p["preferred_listing_type"] ?? "") === "food" ? "selected" : "" ?>>Food</option>
                    <option value="ticket" <?= ($p["preferred_listing_type"] ?? "") === "ticket" ? "selected" : "" ?>>Tickets</option>
                </select>
            </div>
            <div class="form-group">
                <label>Preferred city</label>
                <input name="preferred_city" value="<?= e($p["preferred_city"] ?? "") ?>" placeholder="e.g. Dhaka">
            </div>
            <div class="form-group">
                <label>Preferred area</label>
                <input name="preferred_area" value="<?= e($p["preferred_area"] ?? "") ?>" placeholder="e.g. Dhanmondi">
            </div>
            <button class="primary-button" type="submit">Save Preferences</button>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
