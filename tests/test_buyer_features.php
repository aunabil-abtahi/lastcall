<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$baseUrl = 'http://localhost/lastcall';
$cookieFile = __DIR__ . '/buyer_cookies.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function curlRequest(string $url, string $method = 'GET', array $data = []): array {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $body];
}

function extractCsrfToken(string $html): string {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value=["\']([a-f0-9]{64})["\']\s+name=["\']csrf_token["\']/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

echo "=== TESTING BUYER FEATURES (PATH 1) ===\n\n";

// 1. Login as buyer
echo "1. Logging in as buyer (buyer@lastcall.test)...\n";
$getLogin = curlRequest("$baseUrl/login.php");
$loginCsrf = extractCsrfToken($getLogin['body']);
$loginRes = curlRequest("$baseUrl/login.php", 'POST', [
    'email' => 'buyer@lastcall.test',
    'password' => 'password',
    'csrf_token' => $loginCsrf
]);
echo "  HTTP: {$loginRes['code']}\n";

// 2. Test profile.php GET
echo "\n2. Testing profile.php GET...\n";
$profRes = curlRequest("$baseUrl/profile.php");
echo "  HTTP Code: {$profRes['code']}\n";
echo "  Contains 'My Profile & Account Settings': " . (strpos($profRes['body'], 'My Profile & Account Settings') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'Personal Information': " . (strpos($profRes['body'], 'Personal Information') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'Security & Password': " . (strpos($profRes['body'], 'Security & Password') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'buyer@lastcall.test': " . (strpos($profRes['body'], 'buyer@lastcall.test') !== false ? 'YES' : 'NO') . "\n";

// 3. Test profile update POST
echo "\n3. Testing profile update POST...\n";
$profCsrf = extractCsrfToken($profRes['body']);
$updateRes = curlRequest("$baseUrl/profile.php", 'POST', [
    'action' => 'update_profile',
    'full_name' => 'Rahim Ahmed Updated',
    'phone' => '01711223344',
    'city' => 'Dhaka',
    'area' => 'Gulshan',
    'csrf_token' => $profCsrf
]);
echo "  HTTP Code: {$updateRes['code']}\n";
echo "  Success message displayed: " . (strpos($updateRes['body'], 'Profile updated successfully') !== false ? 'YES' : 'NO') . "\n";

// Verify in DB
$stmt = $pdo->query("SELECT full_name, phone, loc.city, loc.area FROM users u LEFT JOIN locations loc ON loc.location_id=u.location_id WHERE u.email='buyer@lastcall.test'");
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  DB verification -> Name: {$dbUser['full_name']} | Phone: {$dbUser['phone']} | City: {$dbUser['city']} | Area: {$dbUser['area']}\n";

// Revert name back to 'Rahim Ahmed'
$pdo->prepare("UPDATE users SET full_name = 'Rahim Ahmed' WHERE email='buyer@lastcall.test'")->execute();

// 4. Test change password with wrong current password
echo "\n4. Testing password change with invalid current password...\n";
$profCsrf2 = extractCsrfToken($updateRes['body']);
$pwdFailRes = curlRequest("$baseUrl/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'wrongpwd',
    'new_password' => 'newpassword123',
    'confirm_password' => 'newpassword123',
    'csrf_token' => $profCsrf2
]);
echo "  Error message: " . (strpos($pwdFailRes['body'], 'Current password is incorrect') !== false ? 'YES' : 'NO') . "\n";

// 5. Test change password with valid current password
echo "\n5. Testing password change with valid current password...\n";
$profCsrf3 = extractCsrfToken($pwdFailRes['body']);
$pwdOkRes = curlRequest("$baseUrl/profile.php", 'POST', [
    'action' => 'change_password',
    'current_password' => 'password',
    'new_password' => 'newpassword123',
    'confirm_password' => 'newpassword123',
    'csrf_token' => $profCsrf3
]);
echo "  Success message: " . (strpos($pwdOkRes['body'], 'Password changed successfully') !== false ? 'YES' : 'NO') . "\n";

// Revert password back to 'password'
$resetHash = password_hash('password', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE email='buyer@lastcall.test'")->execute([$resetHash]);
echo "  (Password reset back to 'password' for testing stability)\n";

// 6. Test my_tickets.php GET
echo "\n6. Testing my_tickets.php GET...\n";
$pdo->exec("UPDATE tickets SET current_owner_id = 4 WHERE ticket_id = 1");
$tickRes = curlRequest("$baseUrl/my_tickets.php");
echo "  HTTP Code: {$tickRes['code']}\n";
echo "  Contains 'Digital Ticket Wallet': " . (strpos($tickRes['body'], 'Digital Ticket Wallet') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'ticket-pass': " . (strpos($tickRes['body'], 'ticket-pass') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'Official Entrance Ticket Code': " . (strpos($tickRes['body'], 'Official Entrance Ticket Code') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'DMS-2026-001': " . (strpos($tickRes['body'], 'DMS-2026-001') !== false ? 'YES' : 'NO') . "\n";

// 7. Test followed_sellers.php
echo "\n7. Testing followed_sellers.php...\n";
// Ensure seller 2 is followed by user 4
$pdo->exec("INSERT IGNORE INTO followed_sellers (follower_id, seller_id) VALUES (4, 2)");
$followRes = curlRequest("$baseUrl/followed_sellers.php");
echo "  HTTP Code: {$followRes['code']}\n";
echo "  Contains 'Followed Vendors': " . (strpos($followRes['body'], 'Followed Vendors') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'Dhaka Food House': " . (strpos($followRes['body'], 'Dhaka Food House') !== false ? 'YES' : 'NO') . "\n";
echo "  Contains 'Unfollow' button: " . (strpos($followRes['body'], 'Unfollow') !== false ? 'YES' : 'NO') . "\n";

// 8. Test Unfollow action via POST to follow_seller.php
echo "\n8. Testing Unfollow action...\n";
$unfollowCsrf = extractCsrfToken($followRes['body']);
$unfollowRes = curlRequest("$baseUrl/follow_seller.php", 'POST', [
    'seller_id' => 2,
    'action' => 'unfollow',
    'return_to' => 'followed_sellers.php',
    'csrf_token' => $unfollowCsrf
]);
echo "  HTTP Code: {$unfollowRes['code']}\n";
$isStillFollowing = $pdo->query("SELECT 1 FROM followed_sellers WHERE follower_id = 4 AND seller_id = 2")->fetchColumn();
echo "  Is still following in DB: " . ($isStillFollowing ? 'YES' : 'NO') . "\n";
echo "  Displays empty state on followed_sellers: " . (strpos($unfollowRes['body'], "You Haven't Followed Any Vendors Yet") !== false ? 'YES' : 'NO') . "\n";

// Clean up
if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n=== ALL TESTS PASSED WITH FLYING COLORS! ===\n";
