<?php
/**
 * Comprehensive Automated Verification for Phase 6:
 * Security (CSRF Engine & Form Guards), Server-Side Input Validation,
 * Database Views, Schema Integrity, and Regression Testing.
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/csrf.php";

echo "=======================================================\n";
echo "  PHASE 6 AUTOMATED SECURITY & POLISH TEST SUITE\n";
echo "=======================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertCondition(string $desc, bool $cond): void {
    global $testsPassed, $testsFailed;
    if ($cond) {
        echo "  [PASS] $desc\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] $desc\n";
        $testsFailed++;
    }
}

function simulateHttp(string $path, string $method = 'GET', array $postData = [], ?string $cookieJar = null): array {
    $url = "http://localhost/lastcall/" . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't automatically follow redirects so we can inspect 403, 302, 200

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $body];
}

function extractCsrfToken(string $html): ?string {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value=["\']([a-f0-9]{64})["\']\s+name=["\']csrf_token["\']/i', $html, $m)) {
        return $m[1];
    }
    return null;
}

// -------------------------------------------------------------
// SECTION 1: CSRF Protection Engine Unit Tests
// -------------------------------------------------------------
echo "1. Testing CSRF Engine Unit Functions (includes/csrf.php)...\n";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = csrf_token();
assertCondition("csrf_token() returns a 64-character hex string", is_string($token) && strlen($token) === 64 && ctype_xdigit($token));
assertCondition("csrf_token() persists consistently within session", csrf_token() === $token);

$field = csrf_field();
assertCondition("csrf_field() outputs valid hidden input", strpos($field, '<input type="hidden" name="csrf_token"') !== false && strpos($field, $token) !== false);

assertCondition("csrf_verify() accepts matching valid token", csrf_verify($token));
assertCondition("csrf_verify() rejects empty token", !csrf_verify(''));
assertCondition("csrf_verify() rejects null token", !csrf_verify(null));
assertCondition("csrf_verify() rejects forged/tampered token", !csrf_verify('deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef'));

// -------------------------------------------------------------
// SECTION 2: CSRF HTTP Defense Rejection Tests (HTTP 403)
// -------------------------------------------------------------
echo "\n2. Testing HTTP CSRF Defense Rejections (Expecting HTTP 403 Forbidden)...\n";

$anonCookie = tempnam(sys_get_temp_dir(), 'csrf_anon_');

// 2a. POST login without token
$resLoginNoToken = simulateHttp("login.php", "POST", [
    "email" => "food@lastcall.test",
    "password" => "password"
], $anonCookie);
assertCondition("POST login.php without CSRF token returns HTTP 403", $resLoginNoToken['code'] === 403);
assertCondition("Response displays CSRF security token error card", strpos($resLoginNoToken['body'], "Security Token Error") !== false);

// 2b. POST login with tampered token
$resLoginForged = simulateHttp("login.php", "POST", [
    "email" => "food@lastcall.test",
    "password" => "password",
    "csrf_token" => "1111222233334444555566667777888899990000aaaabbbbccccddddeeeeffff"
], $anonCookie);
assertCondition("POST login.php with forged CSRF token returns HTTP 403", $resLoginForged['code'] === 403);

// 2c. POST register.php without token
$resRegisterNoToken = simulateHttp("register.php", "POST", [
    "full_name" => "Hacker",
    "email" => "hacker@test.com",
    "phone" => "01799999999",
    "password" => "password123",
    "terms_accepted" => "1"
], $anonCookie);
assertCondition("POST register.php without CSRF token returns HTTP 403", $resRegisterNoToken['code'] === 403);

// -------------------------------------------------------------
// SECTION 3: Authenticated CSRF Defense Rejection Tests (HTTP 403)
// -------------------------------------------------------------
echo "\n3. Testing Authenticated CSRF Defense Rejections...\n";

// Login as Buyer
$buyerCookie = tempnam(sys_get_temp_dir(), 'buyer_cookie_');
$getLoginBuyer = simulateHttp("login.php", "GET", [], $buyerCookie);
$buyerCsrf = extractCsrfToken($getLoginBuyer['body']);
$postLoginBuyer = simulateHttp("login.php", "POST", [
    "email" => "buyer@lastcall.test",
    "password" => "password",
    "csrf_token" => $buyerCsrf
], $buyerCookie);

assertCondition("Buyer login succeeds", in_array($postLoginBuyer['code'], [200, 302]));

// 3a. Authenticated POST preferences.php without token
$resPrefNoToken = simulateHttp("preferences.php", "POST", [
    "preferred_listing_type" => "food"
], $buyerCookie);
assertCondition("Authenticated POST preferences.php without CSRF token returns HTTP 403", $resPrefNoToken['code'] === 403);

// 3b. Authenticated POST follow_seller.php without token
$resFollowNoToken = simulateHttp("follow_seller.php", "POST", [
    "seller_id" => "2",
    "action" => "follow"
], $buyerCookie);
assertCondition("Authenticated POST follow_seller.php without CSRF token returns HTTP 403", $resFollowNoToken['code'] === 403);

// Login as Admin
$adminCookie = tempnam(sys_get_temp_dir(), 'admin_cookie_');
$getLoginAdmin = simulateHttp("login.php", "GET", [], $adminCookie);
$adminCsrf = extractCsrfToken($getLoginAdmin['body']);
$postLoginAdmin = simulateHttp("login.php", "POST", [
    "email" => "admin@lastcall.test",
    "password" => "admin12345",
    "csrf_token" => $adminCsrf
], $adminCookie);

assertCondition("Admin login succeeds", in_array($postLoginAdmin['code'], [200, 302]));

// 3c. Authenticated POST admin/users.php without token
$resAdminUsersNoToken = simulateHttp("admin/users.php", "POST", [
    "action" => "suspend",
    "target_user_id" => "4"
], $adminCookie);
assertCondition("Authenticated POST admin/users.php without CSRF token returns HTTP 403", $resAdminUsersNoToken['code'] === 403);

// 3d. Authenticated POST admin/seed_demo.php without token
$resAdminSeedNoToken = simulateHttp("admin/seed_demo.php", "POST", [
    "action" => "seed"
], $adminCookie);
assertCondition("Authenticated POST admin/seed_demo.php without CSRF token returns HTTP 403", $resAdminSeedNoToken['code'] === 403);

// -------------------------------------------------------------
// SECTION 4: CSRF HTTP Acceptance Flow
// -------------------------------------------------------------
echo "\n4. Testing HTTP CSRF Token Extraction and Acceptance Flow...\n";

$sellerCookie = tempnam(sys_get_temp_dir(), 'seller_auth_');

// Step A: GET login.php to establish session and receive CSRF token
$getLoginSeller = simulateHttp("login.php", "GET", [], $sellerCookie);
assertCondition("GET login.php returns HTTP 200", $getLoginSeller['code'] === 200);

$sellerToken = extractCsrfToken($getLoginSeller['body']);
assertCondition("Extracted valid CSRF token from login.php HTML form", $sellerToken !== null && strlen($sellerToken) === 64);

// Step B: POST login.php WITH valid extracted token
$postLoginSeller = simulateHttp("login.php", "POST", [
    "email" => "food@lastcall.test",
    "password" => "password",
    "csrf_token" => $sellerToken
], $sellerCookie);

assertCondition("POST login.php with valid CSRF token succeeds (HTTP 302/200)", in_array($postLoginSeller['code'], [200, 302]));

// Step C: Verify authenticated access to protected seller dashboard
$sellerDash = simulateHttp("seller/dashboard.php", "GET", [], $sellerCookie);
assertCondition("Authenticated seller accesses seller/dashboard.php successfully (HTTP 200)", $sellerDash['code'] === 200);
assertCondition("Seller dashboard contains seller business name", strpos($sellerDash['body'], "Dhaka Food House") !== false);

// -------------------------------------------------------------
// SECTION 5: Server-Side Input Validation Edge Cases
// -------------------------------------------------------------
echo "\n5. Testing Server-Side Input Validation Rules...\n";

$valCookie = tempnam(sys_get_temp_dir(), 'val_test_');
$getReg = simulateHttp("register.php", "GET", [], $valCookie);
$regToken = extractCsrfToken($getReg['body']);

// 5a. Invalid phone number (must be numeric digits)
$badPhoneRes = simulateHttp("register.php", "POST", [
    "full_name" => "Test Buyer",
    "email" => "val_phone_" . time() . "@test.com",
    "phone" => "invalid-phone",
    "city" => "Dhaka",
    "area" => "Dhanmondi",
    "password" => "password123",
    "confirm_password" => "password123",
    "terms_accepted" => "1",
    "csrf_token" => $regToken
], $valCookie);
assertCondition("Registration rejects invalid phone format", strpos($badPhoneRes['body'], "Enter a valid phone number") !== false);

// 5b. Invalid email format
$badEmailRes = simulateHttp("register.php", "POST", [
    "full_name" => "Test Buyer",
    "email" => "not-a-valid-email",
    "phone" => "017" . rand(10000000, 99999999),
    "city" => "Dhaka",
    "area" => "Dhanmondi",
    "password" => "password123",
    "confirm_password" => "password123",
    "terms_accepted" => "1",
    "csrf_token" => $regToken
], $valCookie);
assertCondition("Registration rejects invalid email format", strpos($badEmailRes['body'], "Enter a valid email address.") !== false);

// 5c. Short password (< 8 chars)
$badPwdRes = simulateHttp("register.php", "POST", [
    "full_name" => "Test Buyer",
    "email" => "val_pwd_" . time() . "@test.com",
    "phone" => "017" . rand(10000000, 99999999),
    "city" => "Dhaka",
    "area" => "Dhanmondi",
    "password" => "123",
    "confirm_password" => "123",
    "terms_accepted" => "1",
    "csrf_token" => $regToken
], $valCookie);
assertCondition("Registration rejects password shorter than 8 characters", strpos($badPwdRes['body'], "at least 8 characters") !== false);

// 5d. Login with invalid email
$badLoginEmail = simulateHttp("login.php", "POST", [
    "email" => "bad_format_email",
    "password" => "password123",
    "csrf_token" => $regToken
], $valCookie);
assertCondition("Login rejects invalid email format", strpos($badLoginEmail['body'], "Please provide a valid email address.") !== false);

// -------------------------------------------------------------
// SECTION 6: Database Schema, Columns & Views Integrity
// -------------------------------------------------------------
echo "\n6. Testing Database Schema, Columns & Views Integrity...\n";

// 6a. users.account_status column exists
$userCols = $pdo->query("SHOW COLUMNS FROM users LIKE 'account_status'")->fetchAll();
assertCondition("users table has 'account_status' ENUM column", count($userCols) > 0);

// 6b. seller_profiles.rejection_reason column exists
$sellerCols = $pdo->query("SHOW COLUMNS FROM seller_profiles LIKE 'rejection_reason'")->fetchAll();
assertCondition("seller_profiles table has 'rejection_reason' column", count($sellerCols) > 0);

// 6c. view_seller_analytics exists and runs cleanly
$viewAnalytics = $pdo->query("SELECT * FROM view_seller_analytics LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertCondition("view_seller_analytics is operational and readable", is_array($viewAnalytics));
if (is_array($viewAnalytics)) {
    assertCondition("view_seller_analytics contains 'seller_id'", array_key_exists('seller_id', $viewAnalytics));
    assertCondition("view_seller_analytics contains 'business_name'", array_key_exists('business_name', $viewAnalytics));
    assertCondition("view_seller_analytics contains 'total_revenue'", array_key_exists('total_revenue', $viewAnalytics));
    assertCondition("view_seller_analytics contains 'average_rating'", array_key_exists('average_rating', $viewAnalytics));
}

// 6d. view_listing_reviews exists and runs cleanly
$viewReviews = $pdo->query("SELECT * FROM view_listing_reviews LIMIT 1")->fetchAll();
assertCondition("view_listing_reviews is operational and queryable", is_array($viewReviews));

// -------------------------------------------------------------
// SECTION 7: Form CSRF Field Audit Across Application
// -------------------------------------------------------------
echo "\n7. Auditing CSRF Token Injection in Application HTML Forms...\n";

$pagesToAudit = [
    "login.php" => "Login form",
    "register.php" => "Registration form",
];

foreach ($pagesToAudit as $page => $desc) {
    $res = simulateHttp($page, "GET");
    $tokenFound = extractCsrfToken($res['body']);
    assertCondition("$desc ($page) renders <?= csrf_field() ?> input", $tokenFound !== null);
}

// Clean up temp cookie files
@unlink($anonCookie);
@unlink($buyerCookie);
@unlink($adminCookie);
@unlink($sellerCookie);
@unlink($valCookie);

// -------------------------------------------------------------
// TEST SUMMARY
// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "  PHASE 6 TEST RESULTS SUMMARY\n";
echo "  PASSED: $testsPassed\n";
echo "  FAILED: $testsFailed\n";
echo "=======================================================\n";

if ($testsFailed === 0) {
    echo "\n>>> ALL PHASE 6 SECURITY & POLISH TESTS PASSED PERFECTLY! <<<\n\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED! CHECK OUTPUT ABOVE. <<<\n\n";
    exit(1);
}
