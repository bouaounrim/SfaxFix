<?php
/**
 * includes/flash.php
 * ----------------------------
 * Flash message system using sessions.
 *
 * Example:
 * $_SESSION['flash'] = [
 *     'type' => 'success',
 *     'message' => 'Réservation créée !'
 * ];
 *
 * Types:
 * success | danger | warning | info
 */

if (!empty($_SESSION['flash'])):

    $flash = $_SESSION['flash'];

    // Remove message after displaying it once
    unset($_SESSION['flash']);

    // Icons for each message type
    $icons = [
        'success' => '✅',
        'danger'  => '❌',
        'warning' => '⚠️',
        'info'    => 'ℹ️',
    ];

    $icon = $icons[$flash['type']] ?? 'ℹ️';
?>

<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
    <span><?= $icon ?></span>
    <span><?= htmlspecialchars($flash['message']) ?></span>
</div>

<?php endif; ?>