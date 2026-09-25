<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

requireLogin();

$uid = (int) $_SESSION["user_id"];
$profileSuccess = "";
$profileError = "";
$passwordSuccess = "";
$passwordError = "";

// Handle POST Actions with CSRF Protection
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
}

// Handle Profile Details Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_profile") {
    $fullName = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $area = trim($_POST["area"] ?? "");

    if ($fullName === "") {
        $profileError = "Full name cannot be empty.";
    } else {
        try {
            $pdo->beginTransaction();

            $locationId = null;
            if ($city !== "" || $area !== "") {
                $locStmt = $pdo->prepare("SELECT location_id FROM locations WHERE city = ? AND area = ? LIMIT 1");
                $locStmt->execute([$city, $area]);
                $locationId = $locStmt->fetchColumn();

                if (!$locationId) {
                    $insertLoc = $pdo->prepare("INSERT INTO locations (city, area) VALUES (?, ?)");
                    $insertLoc->execute([$city, $area]);
                    $locationId = (int) $pdo->lastInsertId();
                }
            }

            $profilePicturePath = null;
            if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . "/assets/images/users/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileInfo = pathinfo($_FILES["profile_picture"]["name"]);
                $ext = strtolower($fileInfo["extension"] ?? "");
                if (in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"])) {
                    $filename = "user_" . $uid . "_" . time() . "." . $ext;
                    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $uploadDir . $filename)) {
                        $profilePicturePath = "assets/images/users/" . $filename;
                    }
                }
            }

            $updateUser = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, phone = ?, location_id = ?, profile_picture = COALESCE(?, profile_picture)
                WHERE user_id = ?
            ");
            $updateUser->execute([$fullName, $phone ?: null, $locationId, $profilePicturePath, $uid]);

            $pdo->commit();
            $_SESSION["full_name"] = $fullName;
            if ($profilePicturePath) {
                $_SESSION["profile_picture"] = $profilePicturePath;
            }
            $profileSuccess = "Profile updated successfully!";
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (strpos($ex->getMessage(), "phone") !== false) {
                $profileError = "That phone number is already in use by another account.";
            } else {
                $profileError = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Handle Change Password
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "change_password") {
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
        $passwordError = "All password fields are required.";
    } elseif (strlen($newPassword) < 6) {
        $passwordError = "New password must be at least 6 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "New passwords do not match.";
    } else {
        $userStmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1");
        $userStmt->execute([$uid]);
        $hash = $userStmt->fetchColumn();

        if (!$hash || !password_verify($currentPassword, $hash)) {
            $passwordError = "Current password is incorrect.";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePwd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $updatePwd->execute([$newHash, $uid]);
            $passwordSuccess = "Password changed successfully!";
        }
    }
}

// Fetch current user details
$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.account_status, u.created_at, u.profile_picture,
           loc.city, loc.area
    FROM users u
    LEFT JOIN locations loc ON loc.location_id = u.location_id
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$pageTitle = "My Profile | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="container">
    <div class="section-heading">
        <h2>My Profile & Account Settings</h2>
        <p>Manage your personal information, default location for discovery, and account security.</p>
    </div>

    <div class="profile-grid">
        <!-- Personal Details Card -->
        <section class="card">
            <h3>Personal Information</h3>
            <p class="form-intro" style="margin-bottom: 16px;">
                Account Type: <strong style="text-transform: capitalize; color: #172033;"><?= e($user["role"]) ?></strong> | 
                Status: <span class="badge" style="display:inline-block; margin-left: 4px;"><?= e($user["account_status"]) ?></span>
            </p>

            <?php if ($profileSuccess): ?>
                <div class="alert alert-success" style="background: #dff5e5; color: #167234; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
                    <?= e($profileSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if ($profileError): ?>
                <div class="alert alert-error" style="background: #ffe5e5; color: #a32020; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
                    <?= e($profileError) ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group" style="margin-bottom: 14px; display: flex; align-items: center; gap: 16px;">
                    <?php 
                        $avatarUrl = !empty($user["profile_picture"]) ? "/" . htmlspecialchars($user["profile_picture"]) : "https://ui-avatars.com/api/?name=" . urlencode($user["full_name"]) . "&background=random";
                    ?>
                    <img src="<?= $avatarUrl ?>" alt="Profile Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-light);">
                    <div>
                        <label style="display:block; font-weight: bold; margin-bottom: 4px;">Profile Picture</label>
                        <input type="file" name="profile_picture" accept="image/*" style="font-size: 13px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Full Name</label>
                    <input type="text" name="full_name" required value="<?= e($user["full_name"]) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Email Address</label>
                    <input type="email" value="<?= e($user["email"]) ?>" disabled style="width: 100%; padding: 8px 12px; border: 1px solid #e2e6ed; border-radius: 6px; background: #f4f6fa; color: #687080;">
                    <small style="color: #687080; font-size: 12px;">Email cannot be changed.</small>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Phone Number</label>
                    <input type="text" name="phone" value="<?= e($user["phone"] ?? "") ?>" placeholder="+8801..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Default City</label>
                    <input type="text" name="city" value="<?= e($user["city"] ?? "") ?>" placeholder="e.g. Dhaka" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                    <small style="color: #687080; font-size: 12px;">Used to auto-filter nearby deals on the marketplace.</small>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Default Area</label>
                    <input type="text" name="area" value="<?= e($user["area"] ?? "") ?>" placeholder="e.g. Dhanmondi" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                </div>

                <button class="primary-button" type="submit" style="padding: 10px 18px; font-weight: bold; cursor: pointer;">Save Changes</button>
            </form>
        </section>

        <!-- Change Password Card -->
        <section class="card">
            <h3>Security & Password</h3>
            <p class="form-intro" style="margin-bottom: 16px;">Keep your account secure with a strong password.</p>

            <?php if ($passwordSuccess): ?>
                <div class="alert alert-success" style="background: #dff5e5; color: #167234; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
                    <?= e($passwordSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if ($passwordError): ?>
                <div class="alert alert-error" style="background: #ffe5e5; color: #a32020; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px;">
                    <?= e($passwordError) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Current Password</label>
                    <input type="password" name="current_password" required placeholder="Enter current password" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">New Password</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display:block; font-weight: bold; margin-bottom: 4px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="Re-type new password" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd2dc; border-radius: 6px;">
                </div>

                <button class="primary-button" type="submit" style="padding: 10px 18px; font-weight: bold; cursor: pointer;">Update Password</button>
            </form>

            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #edf0f4; color: #8b93a1; font-size: 13px;">
                Member since <?= date("d M Y", strtotime($user["created_at"])) ?>
            </div>
        </section>
    </div>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
