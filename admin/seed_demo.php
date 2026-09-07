<?php
/**
 * Admin Demo Listing Seeder — LastCall
 * Generates realistic surplus food rescue listings and verified event tickets
 * for faculty presentations and live demos.
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth.php";

requireRole("admin");

$message = "";
$error = "";

// Helper to find or create location
function getOrCreateLocation(PDO $pdo, string $city, string $area): int {
    $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE city = ? AND area = ? LIMIT 1");
    $stmt->execute([$city, $area]);
    $locId = $stmt->fetchColumn();
    if ($locId) return (int) $locId;

    $insert = $pdo->prepare("INSERT INTO locations (city, area, address_line) VALUES (?, ?, ?)");
    $insert->execute([$city, $area, "$area Main Road, $city"]);
    return (int) $pdo->lastInsertId();
}

// Helper to find or create event
function getOrCreateEvent(PDO $pdo, int $organizerId, int $locationId, string $name, string $venue, string $startAt): int {
    $stmt = $pdo->prepare("SELECT event_id FROM events WHERE event_name = ? LIMIT 1");
    $stmt->execute([$name]);
    $eventId = $stmt->fetchColumn();
    if ($eventId) return (int) $eventId;

    $insert = $pdo->prepare("
        INSERT INTO events (organizer_id, location_id, event_name, description, venue_name, event_start_at, event_status)
        VALUES (?, ?, ?, ?, ?, ?, 'upcoming')
    ");
    $insert->execute([
        $organizerId,
        $locationId,
        $name,
        "Experience the best live entertainment and networking at $venue.",
        $venue,
        $startAt
    ]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" || isset($_GET["action"])) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        require_csrf();
    }
    $action = $_POST["action"] ?? ($_GET["action"] ?? "seed");
    $rawReturn = $_POST["return_to"] ?? ($_GET["return_to"] ?? "../index.php");
    if (str_contains($rawReturn, "sellers")) {
        $returnTo = "sellers.php";
    } elseif (str_contains($rawReturn, "tickets")) {
        $returnTo = "tickets.php";
    } elseif (str_contains($rawReturn, "seed_demo")) {
        $returnTo = "seed_demo.php";
    } else {
        $returnTo = "../index.php";
    }

    if ($action === "clear") {
        try {
            $pdo->beginTransaction();
            // Delete listings marked with [DEMO] in description or title
            $pdo->exec("
                DELETE FROM listings 
                WHERE description LIKE '%[DEMO]%' OR title LIKE '%[DEMO]%'
            ");
            $pdo->commit();
            $_SESSION["flash_success"] = "All demo listings have been cleared from the marketplace.";
            header("Location: $returnTo");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Failed to clear demo listings: " . $e->getMessage();
        }
    }

    if ($action === "seed") {
        try {
            $pdo->beginTransaction();

            // 1. Select a seller account
            $sellerId = $pdo->query("SELECT user_id FROM users WHERE role = 'seller' ORDER BY user_id ASC LIMIT 1")->fetchColumn();
            if (!$sellerId) {
                $sellerId = $_SESSION["user_id"]; // Fallback to current admin
            }

            // 2. Select locations
            $dhanmondiId = getOrCreateLocation($pdo, "Dhaka", "Dhanmondi");
            $gulshanId = getOrCreateLocation($pdo, "Dhaka", "Gulshan");
            $bananiId = getOrCreateLocation($pdo, "Dhaka", "Banani");
            $chittagongId = getOrCreateLocation($pdo, "Chittagong", "GEC Circle");

            // Curated Demo Food Listings
            $foodDeals = [
                [
                    "title" => "Artisanal Sourdough & Croissant Baker's Box",
                    "description" => "[DEMO] Freshly baked organic sourdough loaf, pain au chocolat, and almond croissants from this morning's batch. Perfectly packaged and ready for pickup.",
                    "category" => "bakery",
                    "price" => 650.00,
                    "location_id" => $dhanmondiId,
                    "minutes_left" => 42, // Triggers "Expiring Soon / Last Chance"!
                    "quantity" => 5,
                    "rules" => [
                        ["threshold" => 120, "discount" => 15.00],
                        ["threshold" => 60, "discount" => 30.00],
                        ["threshold" => 30, "discount" => 50.00]
                    ]
                ],
                [
                    "title" => "Royal Mutton Kacchi Biryani & Firni Feast",
                    "description" => "[DEMO] Premium Basmati mutton kacchi with succulent meat, roasted potatoes, salad, and saffron firni dessert. Surplus evening portions.",
                    "category" => "meal",
                    "price" => 520.00,
                    "location_id" => $gulshanId,
                    "minutes_left" => 85,
                    "quantity" => 6,
                    "rules" => [
                        ["threshold" => 120, "discount" => 10.00],
                        ["threshold" => 60, "discount" => 25.00],
                        ["threshold" => 30, "discount" => 40.00]
                    ]
                ],
                [
                    "title" => "Japanese Teriyaki Chicken Bento Set",
                    "description" => "[DEMO] Grilled teriyaki chicken, steamed jasmine rice, vegetable gyoza, and seaweed salad prepared fresh today.",
                    "category" => "meal",
                    "price" => 480.00,
                    "location_id" => $bananiId,
                    "minutes_left" => 140,
                    "quantity" => 4,
                    "rules" => [
                        ["threshold" => 180, "discount" => 15.00],
                        ["threshold" => 90, "discount" => 30.00],
                        ["threshold" => 45, "discount" => 45.00]
                    ]
                ],
                [
                    "title" => "Fresh Cold-Pressed Juice & Smoothie Trio",
                    "description" => "[DEMO] Pack of 3 raw cold-pressed juices: Valencia Orange, Green Detox (Kale, Apple, Mint), and Berry Blast. No added sugar.",
                    "category" => "beverage",
                    "price" => 360.00,
                    "location_id" => $dhanmondiId,
                    "minutes_left" => 190,
                    "quantity" => 8,
                    "rules" => [
                        ["threshold" => 180, "discount" => 15.00],
                        ["threshold" => 60, "discount" => 30.00]
                    ]
                ],
                [
                    "title" => "Belgian Chocolate Éclair & Macaron Assortment",
                    "description" => "[DEMO] Handcrafted dark chocolate éclairs, pistachio macarons, and raspberry tartlets from our display patisserie.",
                    "category" => "snack",
                    "price" => 450.00,
                    "location_id" => $gulshanId,
                    "minutes_left" => 32, // Triggers "Expiring Soon / Last Chance"!
                    "quantity" => 3,
                    "rules" => [
                        ["threshold" => 120, "discount" => 20.00],
                        ["threshold" => 60, "discount" => 40.00],
                        ["threshold" => 30, "discount" => 60.00]
                    ]
                ],
                [
                    "title" => "Charcoal BBQ Chicken & Garlic Naan Platter",
                    "description" => "[DEMO] Spiced tender charcoal quarter chicken, 2 butter garlic naans, mint chutney, and mixed pickled salad.",
                    "category" => "meal",
                    "price" => 420.00,
                    "location_id" => $chittagongId,
                    "minutes_left" => 110,
                    "quantity" => 7,
                    "rules" => [
                        ["threshold" => 120, "discount" => 15.00],
                        ["threshold" => 60, "discount" => 30.00],
                        ["threshold" => 30, "discount" => 45.00]
                    ]
                ]
            ];

            $stmtInsertListing = $pdo->prepare("
                INSERT INTO listings (seller_id, location_id, listing_type, title, description, original_price, pickup_or_event_deadline, listing_status)
                VALUES (?, ?, 'food', ?, ?, ?, ?, 'active')
            ");

            $stmtInsertFood = $pdo->prepare("
                INSERT INTO food_listing_details (listing_id, food_category, quantity_total, quantity_available, pickup_start_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmtInsertDiscount = $pdo->prepare("
                INSERT INTO discount_rules (listing_id, threshold_minutes, discount_percent)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent)
            ");

            $foodCount = 0;
            foreach ($foodDeals as $item) {
                $deadline = date("Y-m-d H:i:s", time() + ($item["minutes_left"] * 60));
                $stmtInsertListing->execute([
                    $sellerId,
                    $item["location_id"],
                    $item["title"],
                    $item["description"],
                    $item["price"],
                    $deadline
                ]);
                $listingId = (int) $pdo->lastInsertId();

                $stmtInsertFood->execute([
                    $listingId,
                    $item["category"],
                    $item["quantity"],
                    $item["quantity"]
                ]);

                foreach ($item["rules"] as $rule) {
                    $stmtInsertDiscount->execute([
                        $listingId,
                        $rule["threshold"],
                        $rule["discount"]
                    ]);
                }
                $foodCount++;
            }

            // Curated Demo Event Tickets
            $ticketDeals = [
                [
                    "event_name" => "Dhaka Summer Indie Music Festival 2026",
                    "venue" => "ICCB Expo Arena, Kuril, Dhaka",
                    "event_hours" => 5,
                    "location_id" => $gulshanId,
                    "ticket_type" => "General Admission",
                    "price" => 1200.00,
                    "title" => "Dhaka Indie Music Festival — General Admission Pass",
                    "description" => "[DEMO] Live performance by top alternative and indie fusion bands with outdoor food pavilions. Official ticket with verified entry barcode.",
                    "minutes_left" => 180,
                    "rules" => [
                        ["threshold" => 240, "discount" => 15.00],
                        ["threshold" => 120, "discount" => 30.00],
                        ["threshold" => 60, "discount" => 50.00]
                    ]
                ],
                [
                    "event_name" => "National Tech & AI Innovation Summit 2026",
                    "venue" => "BICC Convention Hall, Agargaon, Dhaka",
                    "event_hours" => 8,
                    "location_id" => $dhanmondiId,
                    "ticket_type" => "Delegate Pass",
                    "price" => 1500.00,
                    "title" => "National Tech & AI Summit — Full Day Delegate Pass",
                    "description" => "[DEMO] Access to keynote speeches, AI innovation showcases, tech networking lunch, and startup breakout sessions.",
                    "minutes_left" => 280,
                    "rules" => [
                        ["threshold" => 300, "discount" => 20.00],
                        ["threshold" => 150, "discount" => 35.00]
                    ]
                ],
                [
                    "event_name" => "All-Star Standup Comedy Gala Live",
                    "venue" => "National Theatre Hall, Segunbagicha, Dhaka",
                    "event_hours" => 2,
                    "location_id" => $dhanmondiId,
                    "ticket_type" => "Zone A Seating",
                    "price" => 800.00,
                    "title" => "All-Star Standup Comedy Gala — Front Zone A Ticket",
                    "description" => "[DEMO] 2 hours of non-stop comedy featuring national headliners. Front row Zone A seat with priority entry.",
                    "minutes_left" => 48, // Triggers "Expiring Soon / Last Chance"!
                    "rules" => [
                        ["threshold" => 120, "discount" => 25.00],
                        ["threshold" => 60, "discount" => 50.00],
                        ["threshold" => 30, "discount" => 65.00]
                    ]
                ],
                [
                    "event_name" => "Championship Football Cup Semi-Final",
                    "venue" => "Bangabandhu National Stadium, Dhaka",
                    "event_hours" => 4,
                    "location_id" => $gulshanId,
                    "ticket_type" => "VIP Grandstand",
                    "price" => 950.00,
                    "title" => "Football Cup Semi-Final — VIP Grandstand Seat",
                    "description" => "[DEMO] Thrilling semi-final clash. Covered VIP grandstand ticket with excellent pitch view.",
                    "minutes_left" => 160,
                    "rules" => [
                        ["threshold" => 200, "discount" => 20.00],
                        ["threshold" => 90, "discount" => 35.00]
                    ]
                ],
                [
                    "event_name" => "VIP Sci-Fi Cinema Premiere Night",
                    "venue" => "Star Cineplex VIP Lounge, Bashundhara City",
                    "event_hours" => 1,
                    "location_id" => $dhanmondiId,
                    "ticket_type" => "VIP Recliner",
                    "price" => 750.00,
                    "title" => "Sci-Fi Blockbuster Premiere — VIP Recliner & Popcorn",
                    "description" => "[DEMO] Exclusive opening night screening in Atmos 3D with luxury recliner seating and complimentary snack combo.",
                    "minutes_left" => 38, // Triggers "Expiring Soon / Last Chance"!
                    "rules" => [
                        ["threshold" => 90, "discount" => 30.00],
                        ["threshold" => 45, "discount" => 50.00]
                    ]
                ]
            ];

            $stmtInsertTicketListing = $pdo->prepare("
                INSERT INTO listings (seller_id, location_id, listing_type, title, description, original_price, pickup_or_event_deadline, listing_status)
                VALUES (?, ?, 'ticket', ?, ?, ?, ?, 'active')
            ");

            $stmtInsertTicket = $pdo->prepare("
                INSERT INTO tickets (event_id, current_owner_id, ticket_code, ticket_type, original_price, verification_status, availability_status)
                VALUES (?, ?, ?, ?, ?, 'verified', 'available')
            ");

            $stmtLinkTicket = $pdo->prepare("
                INSERT INTO ticket_listings (listing_id, ticket_id)
                VALUES (?, ?)
            ");

            $ticketCount = 0;
            foreach ($ticketDeals as $tkt) {
                $eventStart = date("Y-m-d H:i:s", time() + ($tkt["event_hours"] * 3600));
                $eventId = getOrCreateEvent($pdo, $sellerId, $tkt["location_id"], $tkt["event_name"], $tkt["venue"], $eventStart);

                $deadline = date("Y-m-d H:i:s", time() + ($tkt["minutes_left"] * 60));
                $stmtInsertTicketListing->execute([
                    $sellerId,
                    $tkt["location_id"],
                    $tkt["title"],
                    $tkt["description"],
                    $tkt["price"],
                    $deadline
                ]);
                $listingId = (int) $pdo->lastInsertId();

                $code = "TKT-" . strtoupper(bin2hex(random_bytes(4)));
                $stmtInsertTicket->execute([
                    $eventId,
                    $sellerId,
                    $code,
                    $tkt["ticket_type"],
                    $tkt["price"]
                ]);
                $ticketId = (int) $pdo->lastInsertId();

                $stmtLinkTicket->execute([$listingId, $ticketId]);

                foreach ($tkt["rules"] as $rule) {
                    $stmtInsertDiscount->execute([
                        $listingId,
                        $rule["threshold"],
                        $rule["discount"]
                    ]);
                }
                $ticketCount++;
            }

            $pdo->commit();

            $_SESSION["flash_success"] = "Generated $foodCount food rescue deals and $ticketCount verified tickets for your faculty demo!";
            header("Location: $returnTo");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error seeding demo listings: " . $e->getMessage();
        }
    }
}

// If accessed directly via GET, show a dedicated admin dashboard control
$pageTitle = "Demo Listing Generator | LastCall Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<main class="container">
    <div class="section-heading">
        <h2>⚡ Faculty Demo Listing Generator</h2>
        <p>Populate the LastCall marketplace with high-quality surplus food deals and verified event tickets for presentations.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 24px; margin-bottom: 36px;">
        <div class="card" style="padding: 24px; border: 1px solid var(--border-subtle); background: white; border-radius: var(--radius-lg);">
            <div style="font-size: 32px; margin-bottom: 12px;">🍽️ + 🎟️</div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--brand-navy); margin-bottom: 8px;">Seed 11 Live Demo Listings</h3>
            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                Generates 6 realistic surplus food items (Biryani, Sourdough box, Bento, Cold-pressed juice, Pastries) and 5 verified event tickets (Indie Music Fest, Tech Summit, Comedy Gala) with dynamic discounts and upcoming deadlines.
            </p>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="seed">
                <button type="submit" class="primary-button" style="background: linear-gradient(135deg, var(--brand-coral) 0%, #dc3c41 100%);">
                    ⚡ Generate Demo Listings Now
                </button>
            </form>
        </div>

        <div class="card" style="padding: 24px; border: 1px solid var(--border-subtle); background: white; border-radius: var(--radius-lg);">
            <div style="font-size: 32px; margin-bottom: 12px;">🧹</div>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--brand-navy); margin-bottom: 8px;">Clear Demo Listings</h3>
            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">
                Removes all previously generated demo listings from the marketplace so you can show faculty an empty state or restart the demonstration cleanly.
            </p>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="primary-button" style="background: #475569;" onclick="return confirm('Clear all demo listings?');">
                    🗑️ Clear Demo Listings
                </button>
            </form>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <a href="../index.php" class="primary-link" style="margin-right: 12px;">← Return to Marketplace</a>
        <a href="sellers.php" class="primary-link" style="background: #334155;">Admin Sellers</a>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
