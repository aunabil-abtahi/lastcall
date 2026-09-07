<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../login.php");
    exit;
}

$sellerId = (int) $_SESSION["user_id"];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
}
$action = trim($_POST["action"] ?? "");

// Handle Listing Removal (POST action=remove or fallback)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($action === "remove" || (!isset($_POST["action"]) && isset($_POST["listing_id"])))) {
    $listingId = filter_input(INPUT_POST, "listing_id", FILTER_VALIDATE_INT);
    if (!$listingId) {
        $_SESSION["seller_message"] = "Invalid listing selected.";
        header("Location: dashboard.php");
        exit;
    }

    $update = $pdo->prepare("
        UPDATE listings 
        SET listing_status = 'removed' 
        WHERE listing_id = ? AND seller_id = ? AND listing_status IN ('draft', 'active', 'expired')
    ");
    $update->execute([$listingId, $sellerId]);

    $_SESSION["seller_message"] = $update->rowCount()
        ? "Listing #$listingId has been removed from the marketplace."
        : "That listing could not be removed or has already been completed.";

    header("Location: dashboard.php");
    exit;
}

// Handle Listing Update (POST action=update)
$errors = [];
$listingId = filter_input(
    $_SERVER["REQUEST_METHOD"] === "POST" ? INPUT_POST : INPUT_GET,
    $_SERVER["REQUEST_METHOD"] === "POST" ? "listing_id" : "id",
    FILTER_VALIDATE_INT
);

if (!$listingId) {
    $_SESSION["seller_message"] = "Invalid listing ID.";
    header("Location: dashboard.php");
    exit;
}

// Fetch listing to verify ownership and editable status
$fetchStmt = $pdo->prepare("
    SELECT
        l.listing_id,
        l.seller_id,
        l.listing_type,
        l.title,
        l.description,
        l.original_price,
        l.pickup_or_event_deadline,
        l.listing_status,
        loc.city,
        loc.area,
        f.food_category,
        f.quantity_available,
        f.pickup_start_at
    FROM listings l
    JOIN locations loc ON loc.location_id = l.location_id
    LEFT JOIN food_listing_details f ON f.listing_id = l.listing_id
    WHERE l.listing_id = ?
      AND l.seller_id = ?
      AND l.listing_status IN ('draft', 'active', 'expired')
    LIMIT 1
");
$fetchStmt->execute([$listingId, $sellerId]);
$listing = $fetchStmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    $_SESSION["seller_message"] = "Listing not found or cannot be modified in its current state.";
    header("Location: dashboard.php");
    exit;
}

$isFood = $listing["listing_type"] === "food";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "update") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = (float) ($_POST["original_price"] ?? 0);
    $rawDeadline = trim($_POST["deadline"] ?? "");
    $deadline = $rawDeadline ? date("Y-m-d H:i:s", strtotime(str_replace("T", " ", $rawDeadline))) : "";

    if ($title === "") {
        $errors[] = "Listing title is required.";
    } elseif (mb_strlen($title) > 200) {
        $errors[] = "Listing title cannot exceed 200 characters.";
    }

    if ($price <= 0) {
        $errors[] = "Original price must be greater than ৳0.";
    }

    if ($rawDeadline === "" || strtotime($deadline) <= time()) {
        $errors[] = "Deadline must be a future date and time.";
    }

    $quantity = 1;
    $foodCategory = "meal";
    $pickupStart = null;

    if ($isFood) {
        $quantity = (int) ($_POST["quantity"] ?? 0);
        if ($quantity < 1) {
            $errors[] = "Quantity available must be at least 1 portion.";
        }

        $foodCategory = trim($_POST["food_category"] ?? "meal");
        if (!in_array($foodCategory, ["meal", "bakery", "beverage", "snack", "other"], true)) {
            $foodCategory = "meal";
        }

        $rawPickupStart = trim($_POST["pickup_start_at"] ?? "");
        if ($rawPickupStart !== "") {
            $pickupStart = date("Y-m-d H:i:s", strtotime(str_replace("T", " ", $rawPickupStart)));
            if (strtotime($pickupStart) > strtotime($deadline)) {
                $errors[] = "Pickup start time cannot be after the pickup deadline.";
            }
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Update listing row
            $updateListingStmt = $pdo->prepare("
                UPDATE listings
                SET title = ?,
                    description = ?,
                    original_price = ?,
                    pickup_or_event_deadline = ?,
                    listing_status = CASE 
                        WHEN listing_status = 'expired' AND ? > NOW() THEN 'active'
                        ELSE listing_status
                    END
                WHERE listing_id = ? AND seller_id = ?
            ");
            $updateListingStmt->execute([
                $title,
                $description ?: null,
                $price,
                $deadline,
                $deadline,
                $listingId,
                $sellerId
            ]);

            // If food, update food_listing_details
            if ($isFood) {
                $updateFoodStmt = $pdo->prepare("
                    UPDATE food_listing_details
                    SET quantity_available = ?,
                        food_category = ?,
                        pickup_start_at = ?
                    WHERE listing_id = ?
                ");
                $updateFoodStmt->execute([
                    $quantity,
                    $foodCategory,
                    $pickupStart,
                    $listingId
                ]);
            }

            $pdo->commit();
            $_SESSION["seller_message"] = "Listing #$listingId has been updated successfully!";
            header("Location: dashboard.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error while updating listing: " . $e->getMessage();
        }
    }
}

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Edit Listing #" . $listingId . " | LastCall Seller";
require_once __DIR__ . "/../includes/header.php";

// Pre-populate values for form
$valTitle = $_POST["title"] ?? $listing["title"];
$valDescription = $_POST["description"] ?? $listing["description"];
$valPrice = $_POST["original_price"] ?? $listing["original_price"];
$valDeadline = $_POST["deadline"] ?? (!empty($listing["pickup_or_event_deadline"]) ? date("Y-m-d\TH:i", strtotime($listing["pickup_or_event_deadline"])) : "");
$valCategory = $_POST["food_category"] ?? ($listing["food_category"] ?? "meal");
$valQuantity = $_POST["quantity"] ?? ($listing["quantity_available"] ?? 1);
$valPickupStart = $_POST["pickup_start_at"] ?? (!empty($listing["pickup_start_at"]) ? date("Y-m-d\TH:i", strtotime($listing["pickup_start_at"])) : "");
?>

<main class="auth-page" style="margin-bottom: 50px;">
    <section class="form-card" style="max-width: 680px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span class="badge <?= $isFood ? "" : "ticket" ?>" style="margin-bottom: 0;">
                <?= $isFood ? "🍽️ Food Rescue Listing" : "🎟️ Event Ticket Listing" ?>
            </span>
            <span class="user-badge <?= e($listing["listing_status"]) ?>">
                Status: <?= e(ucfirst($listing["listing_status"])) ?>
            </span>
        </div>

        <h1 style="margin-top: 10px;">Edit Listing #<?= (int) $listing["listing_id"] ?></h1>
        <p class="form-intro">Update listing details, pricing, available portion quantities, or deadline window.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;">
                <?php foreach ($errors as $err): ?>
                    <p>⚠️ <?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="manage_listing.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="listing_id" value="<?= (int) $listing["listing_id"] ?>">

            <div class="form-group">
                <label for="title">Listing Title</label>
                <input type="text" id="title" name="title" value="<?= e($valTitle) ?>" required maxlength="200">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" placeholder="Describe the food items or ticket details..."><?= e($valDescription) ?></textarea>
            </div>

            <?php if ($isFood): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="food_category">Category</label>
                        <select id="food_category" name="food_category" required>
                            <?php foreach (["meal" => "Meal", "bakery" => "Bakery", "beverage" => "Beverage", "snack" => "Snack", "other" => "Other"] as $k => $lbl): ?>
                                <option value="<?= e($k) ?>" <?= $valCategory === $k ? "selected" : "" ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Available Portions</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="<?= (int) $valQuantity ?>" required>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="original_price">Original Base Price (৳)</label>
                    <input type="number" id="original_price" name="original_price" min="0.01" step="0.01" value="<?= e((string) $valPrice) ?>" required>
                </div>
                <div class="form-group">
                    <label for="deadline"><?= $isFood ? "Pickup Deadline" : "Event / Claim Deadline" ?></label>
                    <input type="datetime-local" id="deadline" name="deadline" value="<?= e($valDeadline) ?>" required>
                </div>
            </div>

            <?php if ($isFood): ?>
                <div class="form-group">
                    <label for="pickup_start_at">Pickup Window Starts (Optional)</label>
                    <input type="datetime-local" id="pickup_start_at" name="pickup_start_at" value="<?= e($valPickupStart) ?>">
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                        Leave blank if available for immediate pickup.
                    </small>
                </div>
            <?php endif; ?>

            <div style="background: var(--surface-muted); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 22px; font-size: 13px; color: var(--text-secondary);">
                📍 <strong>Location:</strong> <?= e($listing["area"]) ?>, <?= e($listing["city"]) ?> 
                <span style="color: var(--text-muted);">(tied to your vendor profile)</span>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="dashboard-button" style="padding: 10px 22px; font-size: 14px; cursor: pointer; border: none;">
                        💾 Save Changes
                    </button>
                    <a href="dashboard.php" class="btn-demo-action studio-btn" style="padding: 10px 18px; line-height: 1.4;">
                        Cancel
                    </a>
                </div>
            </div>
        </form>

        <!-- Danger Zone: Remove Listing -->
        <div style="margin-top: 36px; padding-top: 20px; border-top: 1px dashed var(--border-medium); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <strong style="color: #b91c1c; font-size: 13.5px;">Remove Listing</strong>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Take this deal off the marketplace. It will no longer appear to buyers.
                </p>
            </div>
            <form method="POST" action="manage_listing.php" onsubmit="return confirm('Are you sure you want to permanently remove this listing from the marketplace?');" style="margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="listing_id" value="<?= (int) $listing["listing_id"] ?>">
                <button type="submit" class="btn-demo-action clear-btn" style="padding: 8px 16px; font-size: 13px;">
                    🗑️ Remove Listing
                </button>
            </form>
        </div>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
