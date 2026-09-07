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
    $ticketId = (int) ($_POST["ticket_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($ticketId > 0 && in_array($action, ["approve", "reject"], true)) {
        try {
            $pdo->beginTransaction();

            $ticketQuery = $pdo->prepare("
                SELECT tl.listing_id
                FROM tickets t
                JOIN ticket_listings tl ON tl.ticket_id = t.ticket_id
                WHERE t.ticket_id = ?
                  AND t.verification_status = 'pending'
                LIMIT 1
            ");

            $ticketQuery->execute([$ticketId]);
            $ticket = $ticketQuery->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                if ($action === "approve") {
                    $approveTicket = $pdo->prepare("
                        UPDATE tickets
                        SET verification_status = 'verified',
                            availability_status = 'available'
                        WHERE ticket_id = ?
                    ");

                    $approveTicket->execute([$ticketId]);

                    $activateListing = $pdo->prepare("
                        UPDATE listings
                        SET listing_status = CASE
                            WHEN pickup_or_event_deadline > NOW() THEN 'active'
                            ELSE 'expired'
                        END
                        WHERE listing_id = ?
                    ");

                    $activateListing->execute([$ticket["listing_id"]]);

                    $_SESSION["admin_message"] =
                        "Ticket verified. Its listing is now available to buyers.";
                } else {
                    $rejectTicket = $pdo->prepare("
                        UPDATE tickets
                        SET verification_status = 'rejected',
                            availability_status = 'invalid'
                        WHERE ticket_id = ?
                    ");

                    $rejectTicket->execute([$ticketId]);

                    $removeListing = $pdo->prepare("
                        UPDATE listings
                        SET listing_status = 'removed'
                        WHERE listing_id = ?
                    ");

                    $removeListing->execute([$ticket["listing_id"]]);

                    $_SESSION["admin_message"] =
                        "Ticket rejected and its listing has been removed.";
                }

                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION["admin_message"] =
                "Could not update the ticket verification status.";
        }
    }

    header("Location: tickets.php");
    exit;
}

$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);

$ticketsQuery = $pdo->query("
    SELECT
        t.ticket_id,
        t.ticket_code,
        t.ticket_type,
        t.original_price AS ticket_original_price,
        t.verification_status,
        t.availability_status,
        t.created_at,
        l.listing_id,
        l.title,
        l.original_price AS resale_price,
        l.pickup_or_event_deadline,
        l.listing_status,
        e.event_name,
        e.venue_name,
        e.event_start_at,
        u.full_name AS seller_name,
        u.email AS seller_email
    FROM tickets t
    JOIN ticket_listings tl ON tl.ticket_id = t.ticket_id
    JOIN listings l ON l.listing_id = tl.listing_id
    JOIN events e ON e.event_id = t.event_id
    JOIN users u ON u.user_id = t.current_owner_id
    ORDER BY
        CASE t.verification_status
            WHEN 'pending' THEN 1
            WHEN 'verified' THEN 2
            ELSE 3
        END,
        t.created_at DESC
");

$tickets = $ticketsQuery->fetchAll(PDO::FETCH_ASSOC);

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
$pageTitle = "Ticket Verification | LastCall Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="admin-container">
    <div class="section-heading">
        <h2>Ticket Verification</h2>
        <p>Verify ticket resale submissions before they become public listings.</p>
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
                    <th>Ticket</th>
                    <th>Event</th>
                    <th>Seller</th>
                    <th>Face Price</th>
                    <th>Resale Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td>
                            <strong><?= e($ticket["ticket_type"]) ?></strong>
                            <br>
                            <span><?= e($ticket["ticket_code"]) ?></span>
                        </td>

                        <td>
                            <strong><?= e($ticket["event_name"]) ?></strong>
                            <br>
                            <span><?= e($ticket["venue_name"]) ?></span>
                        </td>

                        <td>
                            <strong><?= e($ticket["seller_name"]) ?></strong>
                            <br>
                            <span><?= e($ticket["seller_email"]) ?></span>
                        </td>

                        <td>৳<?= number_format((float) $ticket["ticket_original_price"], 2) ?></td>
                        <td>৳<?= number_format((float) $ticket["resale_price"], 2) ?></td>

                        <td>
                            <span class="status <?= e($ticket["verification_status"]) ?>">
                                <?= e(ucfirst($ticket["verification_status"])) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($ticket["verification_status"] === "pending"): ?>
                                <form method="POST" class="action-form">
                                    <?= csrf_field() ?>
                                    <input
                                        type="hidden"
                                        name="ticket_id"
                                        value="<?= (int) $ticket["ticket_id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="action"
                                        value="approve"
                                        class="approve-button"
                                    >
                                        Approve
                                    </button>

                                    <button
                                        type="submit"
                                        name="action"
                                        value="reject"
                                        class="reject-button"
                                    >
                                        Reject
                                    </button>
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

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
