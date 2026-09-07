<?php
/**
 * Test rendering of all migrated admin and seller sub-pages
 */

$files = array_merge(glob(__DIR__ . '/../admin/*.php'), glob(__DIR__ . '/../seller/*.php'));
echo "=== 1. LINTING " . count($files) . " PHP FILES ===\n";
$allLintOk = true;
foreach ($files as $f) {
    $out = [];
    $ret = 0;
    exec('"d:\\xampp\\php\\php.exe" -l ' . escapeshellarg($f), $out, $ret);
    if ($ret !== 0) {
        echo "FAIL LINT: " . basename(dirname($f)) . "/" . basename($f) . ": " . implode(" ", $out) . "\n";
        $allLintOk = false;
    } else {
        echo "OK: " . basename(dirname($f)) . "/" . basename($f) . "\n";
    }
}

if (!$allLintOk) {
    echo "Aborting due to lint failure.\n";
    exit(1);
}

echo "\n=== 2. TESTING SIMULATED RENDERING ===\n";

require_once __DIR__ . '/../config/database.php';

// Admin test session (Admin user_id = 1)
$adminUser = $pdo->query("SELECT user_id, full_name, email, role FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Fetch seller users by type
$foodSeller = $pdo->query("SELECT u.user_id, u.full_name, u.email, u.role, sp.seller_type FROM users u JOIN seller_profiles sp ON sp.user_id = u.user_id WHERE sp.seller_type = 'food_business' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ticketSeller = $pdo->query("SELECT u.user_id, u.full_name, u.email, u.role, sp.seller_type FROM users u JOIN seller_profiles sp ON sp.user_id = u.user_id WHERE sp.seller_type = 'individual_ticket_seller' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$eventOrganizer = $pdo->query("SELECT u.user_id, u.full_name, u.email, u.role, sp.seller_type FROM users u JOIN seller_profiles sp ON sp.user_id = u.user_id WHERE sp.seller_type = 'event_organizer' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "Admin User: ID {$adminUser['user_id']} ({$adminUser['full_name']})\n";
echo "Food Seller: ID " . ($foodSeller['user_id'] ?? 'none') . "\n";
echo "Ticket Seller: ID " . ($ticketSeller['user_id'] ?? 'none') . "\n";
echo "Event Organizer: ID " . ($eventOrganizer['user_id'] ?? 'none') . "\n\n";

$runnerFile = __DIR__ . '/run_single_page.php';

$adminPages = [
    'admin/orders.php',
    'admin/reports.php',
    'admin/sellers.php',
    'admin/tickets.php',
    'admin/seed_demo.php'
];

foreach ($adminPages as $p) {
    $sessionExport = var_export([
        'user_id' => $adminUser['user_id'],
        'role' => 'admin',
        'full_name' => $adminUser['full_name'],
        'email' => $adminUser['email']
    ], true);

    $code = "<?php
\$_SERVER['DOCUMENT_ROOT'] = 'd:/XAMPP/htdocs/lastcall';
\$_SERVER['SCRIPT_NAME'] = '/lastcall/{$p}';
\$_SERVER['PHP_SELF'] = '/lastcall/{$p}';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET = [];
session_start();
\$_SESSION = {$sessionExport};
ob_start();
require 'd:/XAMPP/htdocs/lastcall/{$p}';
\$output = ob_get_clean();
\$hasNavbar = strpos(\$output, 'class=\"navbar\"') !== false;
\$hasFooter = strpos(\$output, 'class=\"site-footer\"') !== false;
\$hasLogo = strpos(\$output, 'brand-logo-img') !== false;
\$hasDoctype = strpos(\$output, '<!DOCTYPE html>') !== false;
\$hasHtmlEnd = strpos(\$output, '</html>') !== false;
echo json_encode([
    'bytes' => strlen(\$output),
    'hasNavbar' => \$hasNavbar,
    'hasFooter' => \$hasFooter,
    'hasLogo' => \$hasLogo,
    'hasDoctype' => \$hasDoctype,
    'hasHtmlEnd' => \$hasHtmlEnd
]);
";
    file_put_contents($runnerFile, $code);
    $out = [];
    $ret = 0;
    exec('"d:\\xampp\\php\\php.exe" ' . escapeshellarg($runnerFile), $out, $ret);
    $result = implode("\n", $out);
    $res = json_decode(trim($result), true);

    if ($res && $res['hasNavbar'] && $res['hasFooter'] && $res['hasLogo'] && $res['hasDoctype'] && $res['hasHtmlEnd']) {
        echo "PASS [ADMIN]: {$p} (rendered {$res['bytes']} bytes with shared header+footer)\n";
    } else {
        echo "FAIL [ADMIN]: {$p} => Output: {$result}\n";
    }
}

$sellerTestConfigs = [
    'seller/dashboard.php' => $foodSeller,
    'seller/sales.php' => $foodSeller,
    'seller/create_food_listing.php' => $foodSeller,
    'seller/create_ticket_listing.php' => $ticketSeller,
    'seller/create_event.php' => $eventOrganizer,
    'seller/create_event_ticket.php' => $eventOrganizer
];

foreach ($sellerTestConfigs as $p => $u) {
    $sessionExport = var_export([
        'user_id' => $u['user_id'],
        'role' => 'seller',
        'full_name' => $u['full_name'],
        'email' => $u['email']
    ], true);

    $code = "<?php
\$_SERVER['DOCUMENT_ROOT'] = 'd:/XAMPP/htdocs/lastcall';
\$_SERVER['SCRIPT_NAME'] = '/lastcall/{$p}';
\$_SERVER['PHP_SELF'] = '/lastcall/{$p}';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET = [];
session_start();
\$_SESSION = {$sessionExport};
ob_start();
require 'd:/XAMPP/htdocs/lastcall/{$p}';
\$output = ob_get_clean();
\$hasNavbar = strpos(\$output, 'class=\"navbar\"') !== false;
\$hasFooter = strpos(\$output, 'class=\"site-footer\"') !== false;
\$hasLogo = strpos(\$output, 'brand-logo-img') !== false;
\$hasDoctype = strpos(\$output, '<!DOCTYPE html>') !== false;
\$hasHtmlEnd = strpos(\$output, '</html>') !== false;
echo json_encode([
    'bytes' => strlen(\$output),
    'hasNavbar' => \$hasNavbar,
    'hasFooter' => \$hasFooter,
    'hasLogo' => \$hasLogo,
    'hasDoctype' => \$hasDoctype,
    'hasHtmlEnd' => \$hasHtmlEnd
]);
";
    file_put_contents($runnerFile, $code);
    $out = [];
    $ret = 0;
    exec('"d:\\xampp\\php\\php.exe" ' . escapeshellarg($runnerFile), $out, $ret);
    $result = implode("\n", $out);
    $res = json_decode(trim($result), true);

    if ($res && $res['hasNavbar'] && $res['hasFooter'] && $res['hasLogo'] && $res['hasDoctype'] && $res['hasHtmlEnd']) {
        echo "PASS [SELLER ({$u['seller_type']})]: {$p} (rendered {$res['bytes']} bytes with shared header+footer)\n";
    } else {
        echo "FAIL [SELLER ({$u['seller_type']})]: {$p} => Output: {$result}\n";
    }
}

// Clean up runner
@unlink($runnerFile);

echo "\n=== ALL SUBPAGE RENDERING TESTS COMPLETE ===\n";
