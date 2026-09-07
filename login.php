<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = "";
$successMessage = $_SESSION["success_message"] ?? "";
unset($_SESSION["success_message"]);

$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif ($password === "") {
        $error = "Please enter your password.";
    } else {
        $statement = $pdo->prepare("
            SELECT user_id, full_name, email, password_hash, role
            FROM users
            WHERE email = ?
              AND account_status = 'active'
            LIMIT 1
        ");

        $statement->execute([$email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            header("Location: index.php");
            exit;
        }

        $error = "Incorrect email address or password.";
    }
}

$pageTitle = "Login | LastCall";
require_once __DIR__ . "/includes/header.php";
?>

<main class="auth-page">
    <section class="form-card">
        <h1>Welcome back</h1>
        <p class="form-intro">Sign in to manage your LastCall account.</p>

        <?php if ($successMessage !== ""): ?>
            <div class="alert alert-success">
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="primary-button">
                Login
            </button>
        </form>

        <p class="auth-link">
            Do not have an account?
            <a href="register.php">Create one</a>
        </p>
    </section>
</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>