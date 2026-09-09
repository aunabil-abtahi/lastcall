<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

$errors = [];
$success = "";

$formData = [
    "full_name" => "",
    "email" => "",
    "phone" => "",
    "city" => "",
    "area" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $formData["full_name"] = trim($_POST["full_name"] ?? "");
    $formData["email"] = trim($_POST["email"] ?? "");
    $formData["phone"] = trim($_POST["phone"] ?? "");
    $formData["city"] = trim($_POST["city"] ?? "");
    $formData["area"] = trim($_POST["area"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $termsAccepted = isset($_POST["terms_accepted"]);

    if ($formData["full_name"] === "") {
        $errors[] = "Full name is required.";
    }

    if (!filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if ($formData["phone"] === "") {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $formData["phone"])) {
        $errors[] = "Enter a valid phone number (7-20 digits).";
    }

    if ($formData["city"] === "" || $formData["area"] === "") {
        $errors[] = "City and area are required.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    if (!$termsAccepted) {
        $errors[] = "You must accept the terms and conditions.";
    }

    if (count($errors) === 0) {
        try {
            $pdo->beginTransaction();

            $locationQuery = $pdo->prepare("
                SELECT location_id
                FROM locations
                WHERE city = ? AND area = ?
                LIMIT 1
            ");

            $locationQuery->execute([
                $formData["city"],
                $formData["area"]
            ]);

            $locationId = $locationQuery->fetchColumn();

            if (!$locationId) {
                $insertLocation = $pdo->prepare("
                    INSERT INTO locations (city, area)
                    VALUES (?, ?)
                ");

                $insertLocation->execute([
                    $formData["city"],
                    $formData["area"]
                ]);

                $locationId = $pdo->lastInsertId();
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $insertUser = $pdo->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    phone,
                    password_hash,
                    role,
                    location_id,
                    terms_accepted,
                    terms_version,
                    terms_accepted_at
                ) VALUES (?, ?, ?, ?, 'buyer', ?, 1, 'v1.0', NOW())
            ");

            $insertUser->execute([
                $formData["full_name"],
                $formData["email"],
                $formData["phone"],
                $passwordHash,
                $locationId
            ]);

            $pdo->commit();

          $_SESSION["success_message"] = "Account created successfully. Please sign in.";

            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === "23000") {
                $errors[] = "This email address or phone number is already registered.";
            } else {
                $errors[] = "Something went wrong. Please try again.";
            }
        }
    }
}

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$pageTitle = "Create Account | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Create your account</h1>
        <p class="form-intro">
            Join LastCall to discover discounted food and last-minute tickets.
        </p>

        <?php if (count($errors) > 0): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= e($formData["full_name"]) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($formData["email"]) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?= e($formData["phone"]) ?>"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input
                        type="text"
                        id="city"
                        name="city"
                        placeholder="Example: Dhaka"
                        value="<?= e($formData["city"]) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="area">Area</label>
                    <input
                        type="text"
                        id="area"
                        name="area"
                        placeholder="Example: Dhanmondi"
                        value="<?= e($formData["area"]) ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    required
                >
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="terms_accepted" required>
                <span>I accept the LastCall <a href="terms.php" target="_blank" style="color: var(--brand-coral); text-decoration: underline;">terms and conditions</a>.</span>
            </label>

            <button type="submit" class="primary-button">
                Create Account
            </button>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>