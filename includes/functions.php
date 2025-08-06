<?php
// /includes/functions.php

/**
 * Checks if a user is currently logged in.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user has the 'admin' role.
 */
function isAdmin(): bool {
    if (!isLoggedIn() || !isset($_SESSION['roles'])) {
        return false;
    }
    return in_array('admin', $_SESSION['roles']);
}

/**
 * Checks if the logged-in user has the 'manager' role.
 * Note: An admin is also considered a manager for permission purposes.
 */
function isManager(): bool {
    if (!isLoggedIn() || !isset($_SESSION['roles'])) {
        return false;
    }
    return in_array('manager', $_SESSION['roles']) || in_array('admin', $_SESSION['roles']);
}

/**
 * Checks if the logged-in user has the 'student' role.
 */
function isStudent(): bool {
    if (!isLoggedIn() || !isset($_SESSION['roles'])) {
        return false;
    }
    return in_array('student', $_SESSION['roles']);
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