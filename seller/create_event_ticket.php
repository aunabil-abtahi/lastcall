<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

if (!isLoggedIn() || currentUserRole() !== "seller") {
    header("Location: ../login.php");
    exit;
}

$sellerId = (int) $_SESSION["user_id"];
$eventsQuery = $pdo->prepare("
    SELECT e.event_id, e.event_name, e.event_start_at, e.location_id 
    FROM events e 
    WHERE e.organizer_id = ? 
      AND e.event_status = 'upcoming' 
      AND e.event_start_at > NOW() 
    ORDER BY e.event_start_at
");
$eventsQuery->execute([$sellerId]);
$events = $eventsQuery->fetchAll(PDO::FETCH_ASSOC);

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $eventId = filter_input(INPUT_POST, "event_id", FILTER_VALIDATE_INT);
    $code = trim($_POST["ticket_code"] ?? "");
    $ticketType = trim($_POST["ticket_type"] ?? "");
    $face = filter_input(INPUT_POST, "original_price", FILTER_VALIDATE_FLOAT);
    $price = filter_input(INPUT_POST, "listing_price", FILTER_VALIDATE_FLOAT);

    if (!$eventId || $code === "" || $ticketType === "" || $face === false || $price === false || $face <= 0 || $price <= 0) {
        $error = "Please enter valid ticket details.";
    } else {
        $eventCheck = $pdo->prepare("
            SELECT location_id, event_name, event_start_at 
            FROM events 
            WHERE event_id = ? 
              AND organizer_id = ? 
              AND event_status = 'upcoming' 
              AND event_start_at > NOW()
        ");
        $eventCheck->execute([$eventId, $sellerId]);
        $event = $eventCheck->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            $error = "Please choose one of your valid upcoming events.";
        } else {
            try {
                $pdo->beginTransaction();

                $t = $pdo->prepare("
                    INSERT INTO tickets (event_id, current_owner_id, ticket_code, ticket_type, original_price, verification_status, availability_status) 
                    VALUES (?, ?, ?, ?, ?, 'verified', 'available')
                ");
                $t->execute([$eventId, $sellerId, $code, $ticketType, $face]);
                $ticketId = $pdo->lastInsertId();

                $l = $pdo->prepare("
                    INSERT INTO listings (seller_id, location_id, listing_type, title, description, original_price, pickup_or_event_deadline, listing_status) 
                    VALUES (?, ?, 'ticket', ?, ?, ?, ?, 'active')
                ");
                $l->execute([
                    $sellerId,
                    $event["location_id"],
                    $event["event_name"] . " - " . $ticketType,
                    "Official organizer ticket",
                    $price,
                    $event["event_start_at"]
                ]);
                $listingId = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO ticket_listings (listing_id, ticket_id) VALUES (?, ?)")
                    ->execute([$listingId, $ticketId]);

                $pdo->commit();
                $_SESSION["seller_message"] = "Official ticket is now active on the marketplace.";
                header("Location: dashboard.php");
                exit;
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Ticket could not be saved. Ticket codes must be unique.";
            }
        }
    }
}

$pageTitle = "Create Official Ticket | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Create Official Event Ticket</h1>
        <p class="form-intro">Publish instant-verified tickets directly tied to your organizer event.</p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$events): ?>
            <div class="empty-message" style="text-align:center; padding:2rem;">
                <p>You don't have any upcoming events scheduled.</p>
                <div style="margin-top:1rem;">
                    <a href="create_event.php" class="primary-button">Create an Event First</a>
                </div>
            </div>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="event_id">Event *</label>
                    <select id="event_id" name="event_id" required>
                        <?php foreach ($events as $ev): ?>
                            <option value="<?= (int) $ev["event_id"] ?>" <?= ((int)($_GET["event_id"] ?? 0) === (int)$ev["event_id"]) ? "selected" : "" ?>>
                                <?= e($ev["event_name"]) ?> — <?= e(date("d M Y, h:i A", strtotime($ev["event_start_at"]))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ticket_code">Unique Ticket Code *</label>
                    <input type="text" id="ticket_code" name="ticket_code" required placeholder="e.g. VIP-2026-0901">
                </div>

                <div class="form-group">
                    <label for="ticket_type">Ticket Tier / Type *</label>
                    <input type="text" id="ticket_type" name="ticket_type" placeholder="e.g. General Admission, VIP Lounge" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="original_price">Face Value (৳) *</label>
                        <input type="number" id="original_price" name="original_price" min="1" step="0.01" required placeholder="1200">
                    </div>

                    <div class="form-group">
                        <label for="listing_price">Marketplace Price (৳) *</label>
                        <input type="number" id="listing_price" name="listing_price" min="1" step="0.01" required placeholder="850">
                    </div>
                </div>

                <button class="primary-button" type="submit">Publish Official Ticket</button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
