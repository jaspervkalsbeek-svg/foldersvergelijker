<?php
// ============================================================
// auth.php  –  include at the top of EVERY admin page
// ============================================================
// Usage: add this as the very first line inside every admin
// PHP file (after <?php):
//
//   require_once '../include/auth.php';
//
// That's it. If the user isn't logged in they get redirected
// to login.php automatically.
// ============================================================

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    // TODO: Update this path to match your project folder, e.g. /spikspan/admin/login.php
    $path     = '/foldersvergelijker/admin/login.php';
    header('Location: ' . $protocol . '://' . $host . $path);
    exit;
}