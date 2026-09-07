<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";

requireLogin();
runMarketplaceMaintenance($pdo);

$uid = (int) $_SESSION["user_id"];

// Query all tickets owned by the current user
$stmt = $pdo->prepare("
    SELECT 
        t.ticket_id,
        t.ticket_code,
        t.ticket_type,
        t.original_price,
        t.verification_status,
        t.availability_status,
        e.event_id,
        e.event_name,
        e.venue_name,
        e.event_start_at,
        e.event_status,
        loc.city,
        loc.area,
        organizer.full_name AS organizer_name,
        oi.unit_price AS purchased_price,
        o.order_id,
        o.completed_at
    FROM tickets t
    JOIN events e ON e.event_id = t.event_id
    JOIN locations loc ON loc.location_id = e.location_id
    JOIN users organizer ON organizer.user_id = e.organizer_id
    LEFT JOIN order_items oi ON oi.ticket_id = t.ticket_id
    LEFT JOIN orders o ON o.order_id = oi.order_id AND o.order_status = 'completed'
    WHERE t.current_owner_id = ?
    ORDER BY e.event_start_at DESC
");
$stmt->execute([$uid]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Tickets | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="container">
    <div class="section-heading">
        <h2>My Digital Ticket Wallet</h2>
        <p>Your verified event passes and entrance codes. Present your code at the venue gate for admission.</p>
    </div>

    <?php if (count($tickets) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 20px;">
            <?php foreach ($tickets as $t): ?>
                <?php
                    $isPast = strtotime($t["event_start_at"]) < time();
                    $isCancelled = $t["event_status"] === "cancelled";
                    $statusLabel = $isCancelled ? "Cancelled" : ($isPast ? "Event Concluded" : "Upcoming Event");
                    $statusBg = $isCancelled ? "#ffe5e5" : ($isPast ? "#eef0f4" : "#dff5e5");
                    $statusColor = $isCancelled ? "#a32020" : ($isPast ? "#586174" : "#167234");
                ?>
                <article class="ticket-pass" style="<?= $isPast ? 'opacity: 0.85; filter: grayscale(20%);' : '' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-left: 14px;">
                        <div>
                            <span style="display: inline-block; background: <?= $statusBg ?>; color: <?= $statusColor ?>; font-weight: bold; font-size: 11px; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                <?= $statusLabel ?>
                            </span>
                            <span style="margin-left: 8px; font-size: 12px; color: #8b93a1;">
                                <?= e($t["ticket_type"]) ?>
                            </span>
                        </div>
                        <div style="font-size: 13px; color: #687080;">
                            #<?= (int) $t["ticket_id"] ?>
                        </div>
                    </div>

                    <h3 style="color: #172033; font-size: 20px; margin-bottom: 8px;"><?= e($t["event_name"]) ?></h3>

                    <div class="ticket-details">
                        <p style="margin: 4px 0;">
                            📍 <strong>Venue:</strong> <?= e($t["venue_name"]) ?>, <?= e($t["area"]) ?>, <?= e($t["city"]) ?>
                        </p>
                        <p style="margin: 4px 0;">
                            📅 <strong>Date & Time:</strong> <?= date("l, d M Y — h:i A", strtotime($t["event_start_at"])) ?>
                        </p>
                        <p style="margin: 4px 0;">
                            👤 <strong>Organizer:</strong> <?= e($t["organizer_name"]) ?>
                        </p>
                        <?php if ($t["completed_at"]): ?>
                            <p style="margin: 4px 0; font-size: 12px; color: #8b93a1;">
                                Purchased: <?= date("d M Y", strtotime($t["completed_at"])) ?> 
                                (Paid: ৳<?= number_format((float) ($t["purchased_price"] ?? $t["original_price"]), 2) ?>)
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Digital Pass Code Section -->
                    <div style="margin-top: 18px; padding-left: 14px; background: #f8fafc; border-radius: 8px; padding: 14px; border: 1px solid #e2e8f0; text-align: center;">
                        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; font-weight: 600; display: block; margin-bottom: 6px;">
                            Official Entrance Ticket Code
                        </span>
                        <div class="ticket-code" id="code-<?= (int) $t['ticket_id'] ?>" style="font-size: 18px; padding: 8px 18px; display: inline-block;">
                            <?= e($t["ticket_code"]) ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <button class="btn-sm btn-warning" onclick="navigator.clipboard.writeText('<?= addslashes($t['ticket_code']) ?>'); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy Code', 2000);">
                                Copy Code
                            </button>
                        </div>
                        <p style="margin-top: 8px; font-size: 11px; color: #94a3b8;">
                            Present this code or show this screen to entrance security for validation.
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: 48px 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div style="font-size: 48px; margin-bottom: 14px;">🎟️</div>
            <h3 style="margin-bottom: 8px;">Your Ticket Wallet is Empty</h3>
            <p style="color: #64748b; max-width: 480px; margin: 0 auto 20px;">
                You don't have any event tickets right now. When you reserve and purchase concert, theater, or festival tickets on LastCall, they will appear here.
            </p>
            <a class="primary-button" href="index.php?type=ticket" style="display: inline-block; padding: 10px 20px; text-decoration: none;">
                Browse Event Tickets
            </a>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
