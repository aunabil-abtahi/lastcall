<?php
/**
 * Test Suite: Phase 5 - Admin Platform Management
 * Validates:
 * 1. RBAC & Access Restrictions for Admin suite
 * 2. Executive Dashboard (KPI metrics, quick action hub, transaction feeds)
 * 3. User Directory & Access Control (search, filter, status toggles, self-protection guards)
 * 4. Moderation & Dispute Resolution (status transitions, listing takedowns, user suspensions, self-protection guards)
 * 5. Navigation header links
 */

require_once __DIR__ . "/../config/database.php";

$baseUrl = "http://localhost/lastcall";
$cookieJarAdmin = __DIR__ . "/cookie_admin.txt";
$cookieJarBuyer = __DIR__ . "/cookie_buyer.txt";
$cookieJarGuest = __DIR__ . "/cookie_guest.txt";

@unlink($cookieJarAdmin);
@unlink($cookieJarBuyer);
@unlink($cookieJarGuest);

$passed = 0;
$failed = 0;

function report(string $name, bool $ok, string $details = ""): void {
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo " [PASS] $name\n";
    } else {
        $failed++;
        echo " [FAIL] $name" . ($details !== "" ? " -> $details" : "") . "\n";
    }
}

function curlReq(string $url, string $method = "GET", array $postData = [], string $cookieJar = "", bool $followRedirects = true): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($cookieJar !== "") {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    
    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    
    return [
        "code" => $httpCode,
        "effective_url" => $effectiveUrl,
        "headers" => $headers,
        "body" => $body
    ];
}

echo "=== PHASE 5: ADMIN PLATFORM SUITE VERIFICATION ===\n\n";

// --- 1. RBAC & Access Restrictions ---
echo "--- Group 1: Role-Based Access Control ---\n";
// Unauthenticated access
$resGuestDash = curlReq("$baseUrl/admin/dashboard.php", "GET", [], $cookieJarGuest, false);
report("Guest redirected from admin/dashboard.php", in_array($resGuestDash["code"], [302, 303]) || str_contains($resGuestDash["headers"], "Location:"));

$resGuestUsers = curlReq("$baseUrl/admin/users.php", "GET", [], $cookieJarGuest, false);
report("Guest redirected from admin/users.php", in_array($resGuestUsers["code"], [302, 303]) || str_contains($resGuestUsers["headers"], "Location:"));

$resGuestReports = curlReq("$baseUrl/admin/reports.php", "GET", [], $cookieJarGuest, false);
report("Guest redirected from admin/reports.php", in_array($resGuestReports["code"], [302, 303]) || str_contains($resGuestReports["headers"], "Location:"));

// Authenticate Buyer
$buyerLogin = curlReq("$baseUrl/login.php", "POST", [
    "email" => "buyer@lastcall.test",
    "password" => "password"
], $cookieJarBuyer);
report("Buyer login successful", $buyerLogin["code"] === 200 && str_contains($buyerLogin["effective_url"], "index.php"));

$resBuyerDash = curlReq("$baseUrl/admin/dashboard.php", "GET", [], $cookieJarBuyer, false);
report("Buyer denied access to admin/dashboard.php", in_array($resBuyerDash["code"], [302, 303]) || str_contains($resBuyerDash["headers"], "Location:"));

// Authenticate Admin (Password is admin12345)
$adminLogin = curlReq("$baseUrl/login.php", "POST", [
    "email" => "admin@lastcall.test",
    "password" => "admin12345"
], $cookieJarAdmin);
report("Admin login successful", $adminLogin["code"] === 200 && str_contains($adminLogin["effective_url"], "index.php"));


// --- 2. Executive Dashboard ---
echo "\n--- Group 2: Executive Platform Dashboard (admin/dashboard.php) ---\n";
$resAdminDash = curlReq("$baseUrl/admin/dashboard.php", "GET", [], $cookieJarAdmin);
report("Admin loads dashboard with HTTP 200", $resAdminDash["code"] === 200);
report("Dashboard contains Platform Operations Center header", str_contains($resAdminDash["body"], "Platform Operations Center"));
report("Dashboard renders Gross Volume (GMV) KPI card", str_contains($resAdminDash["body"], "Gross Volume (GMV)") && str_contains($resAdminDash["body"], "৳"));
report("Dashboard renders Items Rescued KPI card", str_contains($resAdminDash["body"], "Items Rescued"));
report("Dashboard renders Approved Vendors KPI card", str_contains($resAdminDash["body"], "Approved Vendors"));
report("Dashboard renders Verified Tickets KPI card", str_contains($resAdminDash["body"], "Verified Tickets"));
report("Dashboard renders Total Accounts KPI card", str_contains($resAdminDash["body"], "Total Accounts"));
report("Dashboard renders Dispute Reports KPI card", str_contains($resAdminDash["body"], "Dispute Reports"));
report("Dashboard renders Quick Action Hub", str_contains($resAdminDash["body"], "Administration Quick Actions"));
report("Dashboard contains Recent Transactions feed", str_contains($resAdminDash["body"], "Recent Transactions"));
report("Dashboard contains Moderation Triage feed", str_contains($resAdminDash["body"], "Moderation Triage"));


// --- 3. User Directory & Access Control ---
echo "\n--- Group 3: User Directory & Access Control (admin/users.php) ---\n";
$resAdminUsers = curlReq("$baseUrl/admin/users.php", "GET", [], $cookieJarAdmin);
report("Admin loads user directory with HTTP 200", $resAdminUsers["code"] === 200);
report("User directory displays User Directory & Access Control heading", str_contains($resAdminUsers["body"], "User Directory") && str_contains($resAdminUsers["body"], "Access Control"));
report("User directory displays user KPI metrics", str_contains($resAdminUsers["body"], "Total Users") && str_contains($resAdminUsers["body"], "Active Buyers"));

// Search test
$resSearch = curlReq("$baseUrl/admin/users.php?q=Admin", "GET", [], $cookieJarAdmin);
report("User search for 'Admin' includes admin account", str_contains($resSearch["body"], "admin@lastcall.test"));

// Role filter test
$resRoleFilter = curlReq("$baseUrl/admin/users.php?role=seller", "GET", [], $cookieJarAdmin);
report("Filter by role=seller returns seller profiles", str_contains($resRoleFilter["body"], "seller") && !str_contains($resRoleFilter["body"], "admin@lastcall.test"));

// Find test buyer user ID
$stmtBuyer = $pdo->prepare("SELECT user_id, account_status FROM users WHERE email = 'buyer@lastcall.test'");
$stmtBuyer->execute();
$testBuyer = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
$testBuyerId = (int) ($testBuyer["user_id"] ?? 0);
report("Identified test buyer user ID ($testBuyerId)", $testBuyerId > 0);

// Self-Protection Guard: Admin cannot suspend own account
$stmtAdmin = $pdo->query("SELECT user_id FROM users WHERE email = 'admin@lastcall.test'");
$adminId = (int) $stmtAdmin->fetchColumn();

$resSelfSuspend = curlReq("$baseUrl/admin/users.php", "POST", [
    "action" => "change_status",
    "user_id" => $adminId,
    "status" => "suspended"
], $cookieJarAdmin);

// Check that admin is still active
$adminStatus = $pdo->query("SELECT account_status FROM users WHERE user_id = $adminId")->fetchColumn();
report("Self-protection guard prevents admin from suspending own account", $adminStatus === "active");

// Change test buyer status to suspended
$resSuspendBuyer = curlReq("$baseUrl/admin/users.php", "POST", [
    "action" => "change_status",
    "user_id" => $testBuyerId,
    "status" => "suspended"
], $cookieJarAdmin);
$buyerStatusSuspended = $pdo->query("SELECT account_status FROM users WHERE user_id = $testBuyerId")->fetchColumn();
report("Admin can suspend user account ($testBuyerId -> suspended)", $buyerStatusSuspended === "suspended");

// Restore test buyer status to active
$resActivateBuyer = curlReq("$baseUrl/admin/users.php", "POST", [
    "action" => "change_status",
    "user_id" => $testBuyerId,
    "status" => "active"
], $cookieJarAdmin);
$buyerStatusActive = $pdo->query("SELECT account_status FROM users WHERE user_id = $testBuyerId")->fetchColumn();
report("Admin can restore user account ($testBuyerId -> active)", $buyerStatusActive === "active");


// --- 4. Moderation Reports & Dispute Resolution ---
echo "\n--- Group 4: Moderation Reports & Dispute Resolution (admin/reports.php) ---\n";
$resReports = curlReq("$baseUrl/admin/reports.php", "GET", [], $cookieJarAdmin);
report("Admin loads reports page with HTTP 200", $resReports["code"] === 200);
report("Reports page contains status filter tabs", str_contains($resReports["body"], "reports.php?status=open") && str_contains($resReports["body"], "reports.php?status=resolved"));

// Create temporary test listing and moderation report
$pdo->beginTransaction();
$pdo->exec("
    INSERT INTO listings (seller_id, location_id, title, description, listing_type, original_price, pickup_or_event_deadline, listing_status)
    VALUES (
        (SELECT seller_profile_id FROM seller_profiles LIMIT 1),
        (SELECT location_id FROM locations LIMIT 1),
        'Phase 5 Test Moderation Target',
        'Item created for moderation takedown verification.',
        'food',
        500.00,
        DATE_ADD(NOW(), INTERVAL 2 DAY),
        'active'
    )
");
$testListingId = (int) $pdo->lastInsertId();

$pdo->exec("
    INSERT INTO reports (reporter_id, reported_user_id, listing_id, report_reason, report_status)
    VALUES (
        $testBuyerId,
        $testBuyerId,
        $testListingId,
        'Misleading listing information during testing.',
        'open'
    )
");
$testReportId = (int) $pdo->lastInsertId();
$pdo->commit();

report("Inserted test listing (#$testListingId) and report (#$testReportId)", $testListingId > 0 && $testReportId > 0);

// Test Status Update to 'reviewing'
$resUpdateReport = curlReq("$baseUrl/admin/reports.php", "POST", [
    "action" => "update_status",
    "report_id" => $testReportId,
    "report_status" => "reviewing"
], $cookieJarAdmin);

$stmtCheckReport = $pdo->prepare("SELECT report_status, reviewed_by FROM reports WHERE report_id = ?");
$stmtCheckReport->execute([$testReportId]);
$repRow = $stmtCheckReport->fetch(PDO::FETCH_ASSOC);
report("Report status updated to 'reviewing' with admin attribution", $repRow["report_status"] === "reviewing" && (int)$repRow["reviewed_by"] === $adminId);

// Test Takedown Listing action
$resTakedown = curlReq("$baseUrl/admin/reports.php", "POST", [
    "action" => "takedown_listing",
    "report_id" => $testReportId,
    "listing_id" => $testListingId
], $cookieJarAdmin);

$listingStatus = $pdo->query("SELECT listing_status FROM listings WHERE listing_id = $testListingId")->fetchColumn();
$reportStatusAfterTakedown = $pdo->query("SELECT report_status FROM reports WHERE report_id = $testReportId")->fetchColumn();
report("Listing takedown sets listing_status = 'removed'", $listingStatus === "removed");
report("Listing takedown resolves the report", $reportStatusAfterTakedown === "resolved");

// Test User Suspension via Report Moderation
// Create second report targeting buyer
$pdo->exec("
    INSERT INTO reports (reporter_id, reported_user_id, listing_id, report_reason, report_status)
    VALUES (
        $adminId,
        $testBuyerId,
        NULL,
        'Violating platform community standards.',
        'open'
    )
");
$testReport2Id = (int) $pdo->lastInsertId();

$resSuspendViaReport = curlReq("$baseUrl/admin/reports.php", "POST", [
    "action" => "suspend_user",
    "report_id" => $testReport2Id,
    "reported_user_id" => $testBuyerId
], $cookieJarAdmin);

$buyerStatusAfterRep = $pdo->query("SELECT account_status FROM users WHERE user_id = $testBuyerId")->fetchColumn();
$report2Status = $pdo->query("SELECT report_status FROM reports WHERE report_id = $testReport2Id")->fetchColumn();
report("User suspension via report sets account_status = 'suspended'", $buyerStatusAfterRep === "suspended");
report("User suspension via report resolves report #$testReport2Id", $report2Status === "resolved");

// Clean up test reports and listing, restore buyer status
$pdo->exec("UPDATE users SET account_status = 'active' WHERE user_id = $testBuyerId");
$pdo->exec("DELETE FROM reports WHERE report_id IN ($testReportId, $testReport2Id)");
$pdo->exec("DELETE FROM listings WHERE listing_id = $testListingId");
report("Cleaned up test reports & restored test buyer status to active", true);


// --- 5. Navigation & Header Integration ---
echo "\n--- Group 5: Navigation & Header Integration ---\n";
$resHeaderAdmin = curlReq("$baseUrl/index.php", "GET", [], $cookieJarAdmin);
report("Header contains Admin Dashboard link", str_contains($resHeaderAdmin["body"], "admin/dashboard.php"));
report("Header contains User Management link", str_contains($resHeaderAdmin["body"], "admin/users.php"));
report("Header contains Seller Verification link", str_contains($resHeaderAdmin["body"], "admin/sellers.php"));
report("Header contains Ticket Verification link", str_contains($resHeaderAdmin["body"], "admin/tickets.php"));


// Cleanup cookies
@unlink($cookieJarAdmin);
@unlink($cookieJarBuyer);
@unlink($cookieJarGuest);

echo "\n============================================\n";
echo "SUMMARY: $passed Passed, $failed Failed\n";
echo "============================================\n";

exit($failed > 0 ? 1 : 0);
