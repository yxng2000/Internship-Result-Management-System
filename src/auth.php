<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
}

function requireRole($role) {
    requireLogin();

    if ($_SESSION['role'] !== $role) {
        header("Location: login.php");
        exit();
    }
}
?>