<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../index.php");
    exit;
}

$sellerId = $_SESSION["user_id"];
$errors = [];

$sellerTypeQuery = $pdo->prepare("
    SELECT seller_type
    FROM seller_profiles
    WHERE user_id = ?
    LIMIT 1
");
$sellerTypeQuery->execute([$sellerId]);

if ($sellerTypeQuery->fetchColumn() !== "food_business") {
    header("Location: dashboard.php");
    exit;
}

$locationQuery = $pdo->prepare("
    SELECT loc.city, loc.area
    FROM users u
    LEFT JOIN locations loc ON loc.location_id = u.location_id
    WHERE u.user_id = ?
");
$locationQuery->execute([$sellerId]);
$defaultLocation = $locationQuery->fetch(PDO::FETCH_ASSOC) ?: [];

$formData = [
    "title" => "",
    "description" => "",
    "food_category" => "meal",
    "original_price" => "",
    "quantity" => "",
    "city" => $defaultLocation["city"] ?? "",
    "area" => $defaultLocation["area"] ?? "",
    "pickup_start_at" => "",
    "deadline" => "",
    "discount_120" => "10",
    "discount_60" => "25",
    "discount_30" => "40"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    foreach ($formData as $field => $default) {
        $formData[$field] = trim($_POST[$field] ?? "");
    }

    $price = (float) $formData["original_price"];
    $quantity = (int) $formData["quantity"];
    $deadline = str_replace("T", " ", $formData["deadline"]);
    $pickupStart = $formData["pickup_start_at"] === ""
        ? null
        : str_replace("T", " ", $formData["pickup_start_at"]);

    if ($formData["title"] === "") {
        $errors[] = "Listing title is required.";
    }

    if (!in_array($formData["food_category"], ["meal", "bakery", "beverage", "snack", "other"], true)) {
        $errors[] = "Please select a food category.";
    }

    if ($price <= 0) {
        $errors[] = "Original price must be greater than zero.";
    }

    if ($quantity <= 0) {
        $errors[] = "Quantity must be at least 1.";
    }

    if ($formData["city"] === "" || $formData["area"] === "") {
        $errors[] = "City and area are required.";
    }

    if ($deadline === "" || strtotime($deadline) <= time()) {
        $errors[] = "Pickup deadline must be in the future.";
    }

    if ($pickupStart !== null && strtotime($pickupStart) >= strtotime($deadline)) {
        $errors[] = "Pickup start time must be before the pickup deadline.";
    }

    $discounts = [
        120 => (float) $formData["discount_120"],
        60 => (float) $formData["discount_60"],
        30 => (float) $formData["discount_30"]
    ];

    foreach ($discounts as $discount) {
        if ($discount < 0 || $discount > 100) {
            $errors[] = "Each discount must be between 0 and 100.";
            break;
        }
    }

    if (count($errors) === 0) {
        try {
            $pdo->beginTransaction();

            $findLocation = $pdo->prepare("
                SELECT location_id
                FROM locations
                WHERE city = ? AND area = ?
                LIMIT 1
            ");
            $findLocation->execute([$formData["city"], $formData["area"]]);
            $locationId = $findLocation->fetchColumn();

            if (!$locationId) {
                $addLocation = $pdo->prepare("
                    INSERT INTO locations (city, area)
                    VALUES (?, ?)
                ");
                $addLocation->execute([$formData["city"], $formData["area"]]);
                $locationId = $pdo->lastInsertId();
            }

            $createListing = $pdo->prepare("
                INSERT INTO listings (
                    seller_id, location_id, listing_type, title, description,
                    original_price, pickup_or_event_deadline, listing_status
                ) VALUES (?, ?, 'food', ?, ?, ?, ?, 'active')
            ");
            $createListing->execute([
                $sellerId,
                $locationId,
                $formData["title"],
                $formData["description"] ?: null,
                $price,
                $deadline
            ]);
            $listingId = $pdo->lastInsertId();

            $createFoodDetails = $pdo->prepare("
                INSERT INTO food_listing_details (
                    listing_id, food_category, quantity_total,
                    quantity_available, pickup_start_at
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $createFoodDetails->execute([
                $listingId,
                $formData["food_category"],
                $quantity,
                $quantity,
                $pickupStart
            ]);

            $createDiscount = $pdo->prepare("
                INSERT INTO discount_rules (
                    listing_id, threshold_minutes, discount_percent
                ) VALUES (?, ?, ?)
            ");

            foreach ($discounts as $threshold => $discount) {
                if ($discount > 0) {
                    $createDiscount->execute([$listingId, $threshold, $discount]);
                }
            }

            $pdo->commit();
            $_SESSION["seller_message"] = "Food listing created successfully.";
            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Could not create the food listing. Please try again.";
        }
    }
}

$pageTitle = "Create Food Listing | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Create food listing</h1>
        <p class="form-intro">Set your quantity, pickup window, and automatic discount tiers.</p>

        <?php if (count($errors) > 0): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_food_listing.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="title">Listing Title</label>
                <input type="text" id="title" name="title" value="<?= e($formData["title"]) ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= e($formData["description"]) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="food_category">Category</label>
                    <select id="food_category" name="food_category" required>
                        <?php foreach (["meal" => "Meal", "bakery" => "Bakery", "beverage" => "Beverage", "snack" => "Snack", "other" => "Other"] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $formData["food_category"] === $value ? "selected" : "" ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="<?= e($formData["quantity"]) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="original_price">Original Price (৳)</label>
                <input type="number" id="original_price" name="original_price" min="1" step="0.01" value="<?= e($formData["original_price"]) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?= e($formData["city"]) ?>" required>
                </div>
                <div class="form-group">
                    <label for="area">Area</label>
                    <input type="text" id="area" name="area" value="<?= e($formData["area"]) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="pickup_start_at">Pickup Starts (optional)</label>
                <input type="datetime-local" id="pickup_start_at" name="pickup_start_at" value="<?= e($formData["pickup_start_at"]) ?>">
            </div>

            <div class="form-group">
                <label for="deadline">Pickup Deadline</label>
                <input type="datetime-local" id="deadline" name="deadline" value="<?= e($formData["deadline"]) ?>" required>
            </div>

            <h3 class="form-subheading">Automatic discounts</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="discount_120">Within 2 hours (%)</label>
                    <input type="number" id="discount_120" name="discount_120" min="0" max="100" step="0.01" value="<?= e($formData["discount_120"]) ?>">
                </div>
                <div class="form-group">
                    <label for="discount_60">Within 1 hour (%)</label>
                    <input type="number" id="discount_60" name="discount_60" min="0" max="100" step="0.01" value="<?= e($formData["discount_60"]) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="discount_30">Within 30 minutes (%)</label>
                <input type="number" id="discount_30" name="discount_30" min="0" max="100" step="0.01" value="<?= e($formData["discount_30"]) ?>">
            </div>

            <button type="submit" class="primary-button">Publish Food Listing</button>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
