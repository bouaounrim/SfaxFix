<?php
/**
 * includes/navbar.php
 * ----------------------------
 * Shared navigation bar displayed on all pages.
 */

$current_page = basename($_SERVER['PHP_SELF']);

// Check if logged user is also a provider
$_is_provider_nav = false;

if (isset($_SESSION['user_id']) && isset($conn)) {

    $nav_check = $conn->prepare(
        "SELECT id FROM prestataires WHERE utilisateur_id = ? LIMIT 1"
    );

    $nav_check->bind_param("i", $_SESSION['user_id']);
    $nav_check->execute();
    $nav_check->store_result();

    $_is_provider_nav = $nav_check->num_rows > 0;

    $nav_check->close();
}
?>

<nav class="navbar">

    <!-- Logo -->
    <a href="/SFAXFIX/index.php" class="navbar-brand">⚡ SfaxFix</a>

    <!-- Mobile Toggle -->
    <button class="navbar-toggle" id="navToggle">☰</button>

    <!-- Navigation -->
    <ul class="navbar-nav" id="navMenu">

        <li>
            <a href="/SFAXFIX/index.php"
               class="<?= $current_page === 'index.php' ? 'active' : '' ?>">
                Accueil
            </a>
        </li>

        <li>
            <a href="/SFAXFIX/pages/providers.php"
               class="<?= $current_page === 'providers.php' ? 'active' : '' ?>">
                Prestataires
            </a>
        </li>

        <?php if (isset($_SESSION['user_id'])): ?>

            <!-- Logged user links -->
            <li>
                <a href="/SFAXFIX/pages/my_bookings.php"
                   class="<?= $current_page === 'my_bookings.php' ? 'active' : '' ?>">
                    Mes Réservations
                </a>
            </li>

            <?php if ($_is_provider_nav): ?>

                <!-- Provider dashboard -->
                <li>
                    <a href="/SFAXFIX/pages/provider_dashboard.php"
                       class="<?= $current_page === 'provider_dashboard.php' ? 'active' : '' ?>">
                        🛠️ Dashboard Prestataire
                    </a>
                </li>

            <?php endif; ?>

            <?php if (($_SESSION['role'] ?? 'user') === 'admin'): ?>

                <!-- Admin panel -->
                <li>
                    <a href="/SFAXFIX/pages/admin.php"
                       class="<?= $current_page === 'admin.php' ? 'active' : '' ?>">
                        🛡️ Administration
                    </a>
                </li>

            <?php endif; ?>

            <!-- Profile -->
            <li>
                <a href="/SFAXFIX/pages/profile.php"
                   class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
                    Mon Profil
                </a>
            </li>

            <!-- Theme Toggle -->
            <li>
                <button id="themeToggle" class="btn btn-outline btn-sm">
                    🌙 Mode
                </button>
            </li>

            <!-- Logout -->
            <li>
                <a href="/SFAXFIX/pages/logout.php" class="btn-nav-cta">
                    Déconnexion
                </a>
            </li>

        <?php else: ?>

            <!-- Guest links -->
            <li>
                <a href="/SFAXFIX/pages/login.php"
                   class="<?= $current_page === 'login.php' ? 'active' : '' ?>">
                    Connexion
                </a>
            </li>

            <li>
                <a href="/SFAXFIX/pages/register.php" class="btn-nav-cta">
                    S'inscrire
                </a>
            </li>

            <!-- Theme Toggle -->
            <li>
                <button id="themeToggle" class="btn btn-outline btn-sm">
                    🌙 Mode
                </button>
            </li>

        <?php endif; ?>

    </ul>

</nav>

<!-- Mobile menu script -->
<script>
document.getElementById('navToggle').addEventListener('click', function () {
    document.getElementById('navMenu').classList.toggle('open');
});
</script>