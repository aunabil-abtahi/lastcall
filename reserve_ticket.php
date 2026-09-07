<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

runMarketplaceMaintenance($pdo);

$buyerId = $_SESSION["user_id"];
$listingId = (int) ($_GET["listing_id"] ?? $_POST["listing_id"] ?? 0);
$error = "";

$ticketQuery = $pdo->prepare("
    SELECT
        l.listing_id,
        l.title,
        l.original_price AS resale_price,
        l.pickup_or_event_deadline,
        t.ticket_id,
        t.ticket_type,
        t.current_owner_id,
        e.event_name,
        e.venue_name,
        e.event_start_at
    FROM listings l
    JOIN ticket_listings tl ON tl.listing_id = l.listing_id
    JOIN tickets t ON t.ticket_id = tl.ticket_id
    JOIN events e ON e.event_id = t.event_id
    WHERE l.listing_id = ?
      AND l.listing_type = 'ticket'
      AND l.listing_status = 'active'
      AND l.pickup_or_event_deadline > NOW()
      AND t.verification_status = 'verified'
      AND t.availability_status = 'available'
    LIMIT 1
");
$ticketQuery->execute([$listingId]);
$ticket = $ticketQuery->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST" && $ticket) {
    require_csrf();
    if ((int) $ticket["current_owner_id"] === $buyerId) {
        $error = "You cannot reserve your own ticket listing.";
    } else {
        try {
            $pdo->beginTransaction();

            $lockedTicketQuery = $pdo->prepare("
                SELECT
                    l.listing_id,
                    l.title,
                    l.original_price AS resale_price,
                    t.ticket_id,
                    t.current_owner_id,
                    t.verification_status,
                    t.availability_status
                FROM listings l
                JOIN ticket_listings tl ON tl.listing_id = l.listing_id
                JOIN tickets t ON t.ticket_id = tl.ticket_id
                WHERE l.listing_id = ?
                  AND l.listing_status = 'active'
                  AND l.pickup_or_event_deadline > NOW()
                FOR UPDATE
            ");
            $lockedTicketQuery->execute([$listingId]);
            $lockedTicket = $lockedTicketQuery->fetch(PDO::FETCH_ASSOC);

            if (
                !$lockedTicket ||
                (int) $lockedTicket["current_owner_id"] === $buyerId ||
                $lockedTicket["verification_status"] !== "verified" ||
                $lockedTicket["availability_status"] !== "available"
            ) {
                $pdo->rollBack();
                $error = "This ticket is no longer available.";
            } else {
                $reserveTicket = $pdo->prepare("
                    UPDATE tickets
                    SET availability_status = 'reserved'
                    WHERE ticket_id = ?
                      AND availability_status = 'available'
                ");
                $reserveTicket->execute([$lockedTicket["ticket_id"]]);

                $createReservation = $pdo->prepare("
                    INSERT INTO reservations (
                        buyer_id, listing_id, ticket_id, quantity,
                        reserved_price, reservation_status, expires_at
                    ) VALUES (?, ?, ?, 1, ?, 'active', DATE_ADD(NOW(), INTERVAL 5 MINUTE))
                ");
                $createReservation->execute([
                    $buyerId,
                    $listingId,
                    $lockedTicket["ticket_id"],
                    $lockedTicket["resale_price"]
                ]);

                $reservationId = $pdo->lastInsertId();
                $pdo->commit();

                header("Location: checkout.php?reservation_id=" . $reservationId);
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Could not reserve this ticket. Please try again.";
        }
    }
}
if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Reserve Ticket | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page" style="padding: 40px 20px 80px;">
    <section class="form-card" style="max-width: 520px; margin: 0 auto; background: white; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <?php if (!$ticket): ?>
            <h1 style="font-size: 1.5rem; color: var(--brand-navy); margin-bottom: 8px;">Ticket unavailable</h1>
            <p class="form-intro" style="color: var(--text-muted); margin-bottom: 20px;">This ticket is already reserved, sold, expired, or unavailable.</p>
            <a class="primary-button" href="index.php" style="display: inline-block; text-decoration: none;">&larr; Back to deals</a>
        <?php else: ?>
            <h1 style="font-size: 1.6rem; color: var(--brand-navy); margin-bottom: 8px;">Reserve Ticket</h1>
            <div class="checkout-summary" style="background: #f8fafc; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 18px; margin-bottom: 18px;">
                <h2 style="font-size: 1.15rem; color: var(--brand-navy); margin: 0 0 6px;"><?= e($ticket["event_name"]) ?></h2>
                <p style="margin: 0 0 4px; color: var(--text-secondary); font-size: 0.9rem;"><?= e($ticket["ticket_type"]) ?> at <?= e($ticket["venue_name"]) ?></p>
                <p style="margin: 0 0 8px; color: var(--text-muted); font-size: 0.85rem;">Event starts: <?= date("d M Y, h:i A", strtotime($ticket["event_start_at"])) ?></p>
                <strong style="font-size: 1.25rem; color: var(--brand-coral);">৳<?= number_format((float) $ticket["resale_price"], 2) ?></strong>
            </div>
            <p class="form-intro" style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 18px;">This ticket will be held for you for <strong>5 minutes</strong>.</p>
            <?php if ($error !== ""): ?><div class="alert alert-error" style="margin-bottom: 16px;"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" action="reserve_ticket.php">
                <?= csrf_field() ?>
                <input type="hidden" name="listing_id" value="<?= (int) $listingId ?>">
                <button type="submit" class="primary-button" style="width: 100%; padding: 12px; font-weight: 700; cursor: pointer;">Reserve Ticket for 5 Minutes</button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
