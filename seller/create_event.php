<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../login.php");
    exit;
}

$sellerId = (int) $_SESSION["user_id"];
$type = $pdo->prepare("SELECT seller_type FROM seller_profiles WHERE user_id = ? AND verification_status = 'approved'");
$type->execute([$sellerId]);

if ($type->fetchColumn() !== "event_organizer") {
    header("Location: dashboard.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $name = trim($_POST["event_name"] ?? "");
    $venue = trim($_POST["venue_name"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $area = trim($_POST["area"] ?? "");
    $start = trim($_POST["event_start_at"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($name === "" || $venue === "" || $city === "" || $area === "" || $start === "") {
        $error = "Please complete all required fields.";
    } elseif (strtotime($start) <= time()) {
        $error = "Event start time must be in the future.";
    } else {
        try {
            $pdo->beginTransaction();
            $loc = $pdo->prepare("SELECT location_id FROM locations WHERE city = ? AND area = ? LIMIT 1");
            $loc->execute([$city, $area]);
            $locationId = $loc->fetchColumn();

            if (!$locationId) {
                $insertLoc = $pdo->prepare("INSERT INTO locations (city, area) VALUES (?, ?)");
                $insertLoc->execute([$city, $area]);
                $locationId = $pdo->lastInsertId();
            }

            $insert = $pdo->prepare("
                INSERT INTO events (organizer_id, location_id, event_name, description, venue_name, event_start_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $sellerId,
                $locationId,
                $name,
                $description,
                $venue,
                date("Y-m-d H:i:s", strtotime($start))
            ]);

            $pdo->commit();
            $_SESSION["seller_message"] = "Event created. You can now create its tickets.";
            header("Location: create_event_ticket.php?event_id=" . $pdo->lastInsertId());
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Event could not be saved.";
        }
    }
}

$pageTitle = "Create Event | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Create an Event</h1>
        <p class="form-intro">Register an upcoming event to issue official tickets on the marketplace.</p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="event_name">Event Name *</label>
                <input type="text" id="event_name" name="event_name" required value="<?= e($_POST['event_name'] ?? '') ?>" placeholder="e.g. Echoes Live Concert">
            </div>

            <div class="form-group">
                <label for="venue_name">Venue Name *</label>
                <input type="text" id="venue_name" name="venue_name" required value="<?= e($_POST['venue_name'] ?? '') ?>" placeholder="e.g. Aloki Convention Center">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" required value="<?= e($_POST['city'] ?? '') ?>" placeholder="Dhaka">
                </div>

                <div class="form-group">
                    <label for="area">Area / Neighborhood *</label>
                    <input type="text" id="area" name="area" required value="<?= e($_POST['area'] ?? '') ?>" placeholder="Tejgaon">
                </div>
            </div>

            <div class="form-group">
                <label for="event_start_at">Event Date &amp; Time *</label>
                <input type="datetime-local" id="event_start_at" name="event_start_at" required value="<?= e($_POST['event_start_at'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="description">Event Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Brief outline of the event, gates opening time, guidelines..."><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <button class="primary-button" type="submit">Create Event &amp; Continue</button>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
