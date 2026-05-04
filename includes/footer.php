<?php
/**
 * includes/footer.php
 * ----------------------------
 * Shared footer displayed on all pages.
 */
?>

<footer class="footer">

    <div class="footer-inner">

        <span class="footer-brand">⚡ SfaxFix</span>

        <ul class="footer-links">
            <li><a href="/SFAXFIX/index.php">Accueil</a></li>
            <li><a href="/SFAXFIX/pages/providers.php">Prestataires</a></li>
            <li><a href="/SFAXFIX/pages/register.php">S'inscrire</a></li>
        </ul>

        <p class="footer-copy">
            &copy; <?= date('Y') ?> SfaxFix — La plateforme de services locaux de Sfax, Tunisie.
            Développé avec ❤️ par Rym Bouaoun.
        </p>

    </div>

</footer>

<!-- ===================================================
     DARK / LIGHT MODE SCRIPT
     Saves theme in localStorage
=================================================== -->
<script>
    // Get saved theme from browser memory
    const savedTheme = localStorage.getItem('theme');

    // If light mode was saved before
    if (savedTheme === 'light') {
        document.body.classList.add('light-mode');
    }

    // Find toggle button
    const themeToggle = document.getElementById('themeToggle');

    // Toggle theme on click
    if (themeToggle) {

        // Update icon on page load
        themeToggle.textContent =
            document.body.classList.contains('light-mode')
                ? '☀️'
                : '🌙';

        themeToggle.addEventListener('click', function () {

            // Toggle class
            document.body.classList.toggle('light-mode');

            // Check current mode
            const isLight = document.body.classList.contains('light-mode');

            // Save choice
            localStorage.setItem('theme', isLight ? 'light' : 'dark');

            // Change icon
            themeToggle.textContent = isLight ? '☀️' : '🌙';
        });
    }
</script>