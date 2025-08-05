<?php
// /includes/functions.php

/**
 * Checks if a user is currently logged in.
 */
function isLoggedIn(): bool {
    // We assume session_start() has already been called
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user is an administrator.
 */
function isAdmin(): bool {
    if (!isLoggedIn()) {
        return false;
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirects the user and terminates the script.
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit();
}

/**
 * A shorthand function to safely escape HTML output.
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}