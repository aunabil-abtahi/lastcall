<?php
/**
 * Shared Header Partial — Role-aware navigation for all pages.
 *
 * Usage:
 *   $pageTitle = "My Page | LastCall";
 *   require_once __DIR__ . "/includes/header.php";   // from root files
 *   require_once __DIR__ . "/../includes/header.php"; // from subdirectories
 */

require_once __DIR__ . "/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect whether we are in a subdirectory (admin/, seller/, payments/, tasks/)
$_headerScriptPath = $_SERVER["SCRIPT_NAME"] ?? "";
$_headerInSubdir = (bool) preg_match('#/(admin|seller|payments|tasks)/#', $_headerScriptPath);
$_base = $_headerInSubdir ? "../" : "";

if (!function_exists("e")) {
    function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
}

$_pageTitle = $pageTitle ?? "LastCall | Hyper-Local Surplus Marketplace";
$_loggedIn = isLoggedIn();
$_role = currentUserRole();
$_userName = currentUserName();
$_cssVersion = file_exists(__DIR__ . "/../assets/css/style.css") ? filemtime(__DIR__ . "/../assets/css/style.css") : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($_pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= $_base ?>assets/images/logo-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $_base ?>assets/css/style.css?v=<?= $_cssVersion ?>">
</head>
<body>

<header class="navbar">
    <div class="nav-container">
        <!-- Brand Logo (Strictly Capped to 30px height) -->
        <a class="logo" href="<?= $_base ?>index.php" title="LastCall Home">
            <img src="<?= $_base ?>assets/images/logo.png" alt="LastCall" class="brand-logo-img" width="120" height="30" style="height:30px; max-height:30px; width:auto; max-width:130px; object-fit:contain; display:block;">
        </a>

        <!-- Primary Discovery Navigation (Left) -->
        <nav class="nav-primary">
            <a href="<?= $_base ?>index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' && empty($_GET['type']) ? 'active' : '' ?>">Browse</a>
            <a href="<?= $_base ?>index.php?type=food" class="nav-link <?= ($_GET['type'] ?? '') === 'food' ? 'active' : '' ?>">
                <span class="nav-icon">🍽️</span> Food Rescue
            </a>
            <a href="<?= $_base ?>index.php?type=ticket" class="nav-link <?= ($_GET['type'] ?? '') === 'ticket' ? 'active' : '' ?>">
                <span class="nav-icon">🎟️</span> Event Tickets
            </a>
            <a href="<?= $_base ?>recommendations.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'recommendations.php' ? 'active' : '' ?>">
                <span class="nav-icon">✨</span> For You
            </a>
        </nav>

        <!-- User Controls / Account Navigation (Right) -->
        <div class="nav-actions">
            <?php if ($_loggedIn): ?>
                <!-- Dropdown Menu for Organized Navigation -->
                <div class="user-menu" id="userMenu">
                    <button type="button" class="user-menu-btn" onclick="toggleUserMenu(event)" aria-haspopup="true" aria-expanded="false" id="userMenuBtn">
                        <span class="user-avatar-circle"><?= mb_strtoupper(mb_substr($_userName, 0, 1)) ?></span>
                        <span class="user-menu-name"><?= e($_userName) ?></span>
                        <span class="user-badge <?= e($_role) ?>"><?= e(ucfirst($_role)) ?></span>
                        <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div class="user-dropdown-panel" id="userDropdownPanel">
                        <div class="dropdown-profile-brief">
                            <div class="dropdown-profile-avatar"><?= mb_strtoupper(mb_substr($_userName, 0, 1)) ?></div>
                            <div class="dropdown-profile-info">
                                <div class="dropdown-profile-name"><?= e($_userName) ?></div>
                                <div class="dropdown-profile-role"><?= e(ucfirst($_role)) ?> Account</div>
                            </div>
                        </div>

                        <div class="dropdown-group">
                            <a href="<?= $_base ?>my_orders.php" class="dropdown-link">
                                <span class="dd-icon">📦</span> My Orders
                            </a>
                            <a href="<?= $_base ?>my_tickets.php" class="dropdown-link">
                                <span class="dd-icon">🎟️</span> My Tickets Wallet
                            </a>
                            <a href="<?= $_base ?>preferences.php" class="dropdown-link">
                                <span class="dd-icon">⚙️</span> Deal Preferences
                            </a>
                            <a href="<?= $_base ?>followed_sellers.php" class="dropdown-link">
                                <span class="dd-icon">❤️</span> Followed Vendors
                            </a>
                            <a href="<?= $_base ?>leaderboard.php" class="dropdown-link">
                                <span class="dd-icon">🏆</span> Community Leaderboard
                            </a>
                        </div>

                        <?php if ($_role === "buyer"): ?>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-group">
                                <a href="<?= $_base ?>seller_apply.php" class="dropdown-link highlight-link">
                                    <span class="dd-icon">🏪</span> Become a Verified Seller
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($_role === "seller"): ?>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-label">SELLER PORTAL</div>
                            <div class="dropdown-group">
                                <a href="<?= $_base ?>seller/dashboard.php" class="dropdown-link">
                                    <span class="dd-icon">📊</span> Seller Dashboard
                                </a>
                                <a href="<?= $_base ?>seller/sales.php" class="dropdown-link">
                                    <span class="dd-icon">💰</span> Sales Records
                                </a>
                                <a href="<?= $_base ?>seller/reviews.php" class="dropdown-link">
                                    <span class="dd-icon">⭐</span> Customer Reviews
                                </a>
                                <a href="<?= $_base ?>seller/create_food_listing.php" class="dropdown-link">
                                    <span class="dd-icon">➕</span> Post Surplus Food
                                </a>
                                <a href="<?= $_base ?>seller/create_ticket_listing.php" class="dropdown-link">
                                    <span class="dd-icon">➕</span> List Event Ticket
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($_role === "admin"): ?>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-label">ADMINISTRATION &amp; DEMO</div>
                            <div class="dropdown-group">
                                <a href="<?= $_base ?>admin/dashboard.php" class="dropdown-link highlight-link">
                                    <span class="dd-icon">📊</span> <strong>Admin Dashboard</strong>
                                </a>
                                <a href="<?= $_base ?>admin/users.php" class="dropdown-link">
                                    <span class="dd-icon">👥</span> User Management
                                </a>
                                <a href="<?= $_base ?>admin/sellers.php" class="dropdown-link">
                                    <span class="dd-icon">🛡️</span> Seller Verification
                                </a>
                                <a href="<?= $_base ?>admin/tickets.php" class="dropdown-link">
                                    <span class="dd-icon">🎫</span> Ticket Verification
                                </a>
                                <a href="<?= $_base ?>admin/orders.php" class="dropdown-link">
                                    <span class="dd-icon">📋</span> Platform Orders
                                </a>
                                <a href="<?= $_base ?>admin/reports.php" class="dropdown-link">
                                    <span class="dd-icon">🚩</span> Moderation Reports
                                </a>
                                <form action="<?= $_base ?>admin/seed_demo.php" method="POST" style="margin: 0; padding: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="seed">
                                    <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
                                    <button type="submit" class="dropdown-link" style="width: 100%; border: none; cursor: pointer; text-align: left; font-family: inherit;">
                                        <span class="dd-icon">⚡</span> Seed Demo Listings
                                    </button>
                                </form>
                                <a href="<?= $_base ?>admin/seed_demo.php" class="dropdown-link">
                                    <span class="dd-icon">🛠️</span> Demo Data Studio
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>
                        <div class="dropdown-group">
                            <a href="<?= $_base ?>profile.php" class="dropdown-link">
                                <span class="dd-icon">👤</span> Profile &amp; Settings
                            </a>
                            <a href="<?= $_base ?>logout.php" class="dropdown-link logout-link">
                                <span class="dd-icon">🚪</span> Sign Out
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <a href="<?= $_base ?>leaderboard.php" class="nav-link">
                    <span class="nav-icon">🏆</span> Leaderboard
                </a>
                <a href="<?= $_base ?>login.php" class="btn-ghost-login">Log In</a>
                <a href="<?= $_base ?>register.php" class="btn-primary-coral">Get Started</a>
            <?php endif; ?>

            <!-- Mobile Hamburger Toggle -->
            <button type="button" class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle navigation menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Drawer Backdrop Overlay -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="toggleMobileMenu()"></div>

<!-- Mobile Navigation Drawer -->
<div class="mobile-nav-drawer" id="mobileNavDrawer">
    <a href="<?= $_base ?>index.php" class="mobile-nav-link">Browse Deals</a>
    <a href="<?= $_base ?>index.php?type=food" class="mobile-nav-link">🍽️ Food Rescue</a>
    <a href="<?= $_base ?>index.php?type=ticket" class="mobile-nav-link">🎟️ Event Tickets</a>
    <a href="<?= $_base ?>recommendations.php" class="mobile-nav-link">✨ Recommended</a>
    <a href="<?= $_base ?>leaderboard.php" class="mobile-nav-link">🏆 Leaderboard</a>

    <?php if ($_loggedIn): ?>
        <div class="mobile-drawer-divider"></div>
        <a href="<?= $_base ?>my_orders.php" class="mobile-nav-link">📦 My Orders</a>
        <a href="<?= $_base ?>my_tickets.php" class="mobile-nav-link">🎟️ My Tickets</a>
        <a href="<?= $_base ?>preferences.php" class="mobile-nav-link">⚙️ Preferences</a>
        <a href="<?= $_base ?>followed_sellers.php" class="mobile-nav-link">❤️ Followed Vendors</a>

        <?php if ($_role === "seller"): ?>
            <div class="mobile-drawer-divider"></div>
            <a href="<?= $_base ?>seller/dashboard.php" class="mobile-nav-link">📊 Seller Dashboard</a>
            <a href="<?= $_base ?>seller/sales.php" class="mobile-nav-link">💰 Sales Records</a>
        <?php endif; ?>

        <?php if ($_role === "admin"): ?>
            <div class="mobile-drawer-divider"></div>
            <a href="<?= $_base ?>admin/dashboard.php" class="mobile-nav-link">📊 Admin Dashboard</a>
            <a href="<?= $_base ?>admin/users.php" class="mobile-nav-link">👥 User Management</a>
            <a href="<?= $_base ?>admin/sellers.php" class="mobile-nav-link">🛡️ Seller Verification</a>
            <a href="<?= $_base ?>admin/tickets.php" class="mobile-nav-link">🎫 Ticket Verification</a>
            <a href="<?= $_base ?>admin/orders.php" class="mobile-nav-link">📋 Orders</a>
            <a href="<?= $_base ?>admin/reports.php" class="mobile-nav-link">🚩 Reports</a>
        <?php endif; ?>

        <div class="mobile-drawer-divider"></div>
        <a href="<?= $_base ?>profile.php" class="mobile-nav-link">👤 Profile</a>
        <a href="<?= $_base ?>logout.php" class="mobile-nav-link logout-link">🚪 Logout</a>
    <?php else: ?>
        <div class="mobile-drawer-divider"></div>
        <a href="<?= $_base ?>login.php" class="mobile-nav-link">Log In</a>
        <a href="<?= $_base ?>register.php" class="mobile-nav-link" style="color: var(--brand-coral); font-weight: 700;">Sign Up</a>
    <?php endif; ?>
</div>
<?php if (!empty($_SESSION["flash_success"])): ?>
    <div class="flash-banner-wrap" style="width: min(1240px, 92%); margin: 16px auto 0;">
        <div class="alert alert-success" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>✨</span>
                <span><?= e($_SESSION["flash_success"]) ?></span>
            </div>
            <button type="button" onclick="this.closest('.flash-banner-wrap').remove();" style="background:none; border:none; font-size:20px; line-height:1; cursor:pointer; color:inherit; opacity:0.7; padding: 0 4px;">&times;</button>
        </div>
    </div>
    <?php unset($_SESSION["flash_success"]); ?>
<?php endif; ?>

<script>
function toggleUserMenu(e) {
    if (e) e.stopPropagation();
    const panel = document.getElementById('userDropdownPanel');
    const btn = document.getElementById('userMenuBtn');
    if (panel) {
        const isShown = panel.classList.toggle('show');
        if (btn) btn.setAttribute('aria-expanded', isShown ? 'true' : 'false');
    }
}

function toggleMobileMenu() {
    const drawer = document.getElementById('mobileNavDrawer');
    const backdrop = document.getElementById('drawerBackdrop');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    if (drawer) {
        const isOpen = drawer.classList.toggle('open');
        if (backdrop) backdrop.classList.toggle('open', isOpen);
        if (toggleBtn) toggleBtn.classList.toggle('open', isOpen);
        document.body.classList.toggle('mobile-nav-open', isOpen);
    }
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    const userMenu = document.getElementById('userMenu');
    const panel = document.getElementById('userDropdownPanel');
    if (panel && userMenu && !userMenu.contains(e.target)) {
        panel.classList.remove('show');
        const btn = document.getElementById('userMenuBtn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
});

// ESC key to dismiss menus
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const drawer = document.getElementById('mobileNavDrawer');
        if (drawer && drawer.classList.contains('open')) {
            toggleMobileMenu();
        }
        const panel = document.getElementById('userDropdownPanel');
        if (panel && panel.classList.contains('show')) {
            panel.classList.remove('show');
            const btn = document.getElementById('userMenuBtn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }
});
</script>
