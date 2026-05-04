<?php
/**
 * pages/logout.php
 * ===================================================
 * Logout Page — SfaxFix
 * ===================================================
 *
 * This page logs the user out.
 *
 * FLOW:
 *  1. Start the session
 *  2. Remove session data
 *  3. Destroy the session
 *  4. Redirect to login page
 */

session_start();

// Optional: keep user name before logout
$user_name = $_SESSION['nom'] ?? '';

// ─────────────────────────────────────────────
// Clear all session variables
// ─────────────────────────────────────────────
session_unset();

// ─────────────────────────────────────────────
// Destroy the session
// ─────────────────────────────────────────────
session_destroy();

// ─────────────────────────────────────────────
// Redirect to login page
// ─────────────────────────────────────────────
header("Location: login.php?logged_out=1");
exit();
?>