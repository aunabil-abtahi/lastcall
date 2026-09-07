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
$sellerType = $sellerTypeQuery->fetchColumn();

if ($sellerType !== "individual_ticket_seller") {
    header("Location: dashboard.php");
    exit;
}

$eventsQuery = $pdo->query("
    SELECT
        e.event_id,
        e.event_name,
        e.venue_name,
        e.event_start_at,
        e.location_id,
        loc.city,
        loc.area
    FROM events e
    JOIN locations loc ON e.location_id = loc.location_id
    WHERE e.event_status = 'upcoming'
      AND e.event_start_at > NOW()
    ORDER BY e.event_start_at ASC
");

$events = $eventsQuery->fetchAll(PDO::FETCH_ASSOC);

$eventsById = [];
foreach ($events as $event) {
    $eventsById[$event["event_id"]] = $event;
}

$formData = [
    "event_id" => "",
    "ticket_code" => "",
    "ticket_type" => "",
    "original_price" => "",
    "resale_price" => "",
    "description" => "",
    "deadline" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $formData["event_id"] = (int) ($_POST["event_id"] ?? 0);
    $formData["ticket_code"] = trim($_POST["ticket_code"] ?? "");
    $formData["ticket_type"] = trim($_POST["ticket_type"] ?? "");
    $formData["original_price"] = trim($_POST["original_price"] ?? "");
    $formData["resale_price"] = trim($_POST["resale_price"] ?? "");
    $formData["description"] = trim($_POST["description"] ?? "");
    $formData["deadline"] = trim($_POST["deadline"] ?? "");

    $originalPrice = (float) $formData["original_price"];
    $resalePrice = (float) $formData["resale_price"];
    $deadline = str_replace("T", " ", $formData["deadline"]);

    if (!isset($eventsById[$formData["event_id"]])) {
        $errors[] = "Please choose a valid upcoming event.";
    }

    if ($formData["ticket_code"] === "") {
        $errors[] = "Ticket code is required.";
    }

    if ($formData["ticket_type"] === "") {
        $errors[] = "Ticket type is required.";
    }

    if ($originalPrice <= 0) {
        $errors[] = "Original ticket price must be greater than zero.";
    }

    if ($resalePrice <= 0) {
        $errors[] = "Resale price must be greater than zero.";
    }

    if ($resalePrice > $originalPrice) {
        $errors[] = "Resale price cannot be greater than the original ticket price.";
    }

    if ($deadline === "" || strtotime($deadline) <= time()) {
        $errors[] = "Listing deadline must be in the future.";
    }

    if (
        isset($eventsById[$formData["event_id"]]) &&
        strtotime($deadline) >= strtotime($eventsById[$formData["event_id"]]["event_start_at"])
    ) {
        $errors[] = "Listing deadline must be before the event starts.";
    }

    if (count($errors) === 0) {
        try {
            $pdo->beginTransaction();

            $selectedEvent = $eventsById[$formData["event_id"]];

            $insertTicket = $pdo->prepare("
                INSERT INTO tickets (
                    event_id,
                    current_owner_id,
                    ticket_code,
                    ticket_type,
                    original_price,
                    verification_status,
                    availability_status
                ) VALUES (?, ?, ?, ?, ?, 'pending', 'available')
            ");

            $insertTicket->execute([
                $formData["event_id"],
                $sellerId,
                $formData["ticket_code"],
                $formData["ticket_type"],
                $originalPrice
            ]);

            $ticketId = $pdo->lastInsertId();

            $listingTitle =
                $selectedEvent["event_name"] .
                " - " .
                $formData["ticket_type"] .
                " Ticket";

            $description = $formData["description"] !== ""
                ? $formData["description"]
                : "Ticket submitted for verification.";

            $insertListing = $pdo->prepare("
                INSERT INTO listings (
                    seller_id,
                    location_id,
                    listing_type,
                    title,
                    description,
                    original_price,
                    pickup_or_event_deadline,
                    listing_status
                ) VALUES (?, ?, 'ticket', ?, ?, ?, ?, 'draft')
            ");

            $insertListing->execute([
                $sellerId,
                $selectedEvent["location_id"],
                $listingTitle,
                $description,
                $resalePrice,
                $deadline
            ]);

            $listingId = $pdo->lastInsertId();

            $connectTicketListing = $pdo->prepare("
                INSERT INTO ticket_listings (listing_id, ticket_id)
                VALUES (?, ?)
            ");

            $connectTicketListing->execute([$listingId, $ticketId]);

            $pdo->commit();

            $_SESSION["seller_message"] =
                "Ticket submitted. It is waiting for admin verification.";

            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === "23000") {
                $errors[] = "That ticket code is already registered.";
            } else {
                $errors[] = "Could not submit the ticket. Please try again.";
            }
        }
    }
}

$pageTitle = "Create Ticket Listing | LastCall";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Submit a ticket for resale</h1>
        <p class="form-intro">
            The ticket will remain hidden until an administrator verifies it.
        </p>

        <?php if (count($errors) > 0): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($events) === 0): ?>
            <div class="alert alert-error">
                No upcoming events are currently available.
            </div>
        <?php else: ?>
            <form method="POST" action="create_ticket_listing.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="event_id">Event</label>
                    <select id="event_id" name="event_id" required>
                        <option value="">Select an event</option>

                        <?php foreach ($events as $event): ?>
                            <option
                                value="<?= (int) $event["event_id"] ?>"
                                <?= (string) $formData["event_id"] === (string) $event["event_id"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= e($event["event_name"]) ?>
                                - <?= e($event["venue_name"]) ?>
                                (<?= date("d M Y, h:i A", strtotime($event["event_start_at"])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ticket_code">Ticket Code</label>
                    <input
                        type="text"
                        id="ticket_code"
                        name="ticket_code"
                        placeholder="Example: DMS-2026-101"
                        value="<?= e($formData["ticket_code"]) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="ticket_type">Ticket Type</label>
                    <input
                        type="text"
                        id="ticket_type"
                        name="ticket_type"
                        placeholder="Example: General Admission"
                        value="<?= e($formData["ticket_type"]) ?>"
                        required
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="original_price">Original Price (৳)</label>
                        <input
                            type="number"
                            id="original_price"
                            name="original_price"
                            min="1"
                            step="0.01"
                            value="<?= e($formData["original_price"]) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="resale_price">Resale Price (৳)</label>
                        <input
                            type="number"
                            id="resale_price"
                            name="resale_price"
                            min="1"
                            step="0.01"
                            value="<?= e($formData["resale_price"]) ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="deadline">Listing Deadline</label>
                    <input
                        type="datetime-local"
                        id="deadline"
                        name="deadline"
                        value="<?= e($formData["deadline"]) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <input
                        type="text"
                        id="description"
                        name="description"
                        placeholder="Optional ticket details"
                        value="<?= e($formData["description"]) ?>"
                    >
                </div>

                <button type="submit" class="primary-button">
                    Submit Ticket for Verification
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>