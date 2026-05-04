<?php

/**
 * includes/auth.php
 * ----------------------------
 * Authentication helper functions.
 *
 * Usage:
 * include("../includes/auth.php");
 *
 * require_login(); // User must be logged in
 * require_admin(); // User must be admin
 */


/**
 * Redirects to login page if user is not logged in.
 *
 * @param string $message Optional message
 */
function require_login($message = 'Vous devez être connecté pour accéder à cette page.') {

    if (!isset($_SESSION['user_id'])) {

        $_SESSION['flash'] = [
            'type'    => 'warning',
            'message' => $message
        ];

        header("Location: /SfaxFix/pages/login.php");
        exit();
    }
}


/**
 * Redirects if logged user is not admin.
 */
function require_admin() {

    // User must be logged in first
    require_login('Accès réservé aux administrateurs.');

    // Check admin role
    if (($_SESSION['role'] ?? 'user') !== 'admin') {

        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => '🚫 Accès refusé. Cette page est réservée aux administrateurs.'
        ];

        header("Location: /SfaxFix/pages/dashboard.php");
        exit();
    }
}


/**
 * Returns true if user is logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}


/**
 * Returns true if user is admin.
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? 'user') === 'admin';
}


/**
 * Returns current user ID.
 * Returns 0 if not logged in.
 */
function current_user_id() {
    return (int)($_SESSION['user_id'] ?? 0);
}

?>