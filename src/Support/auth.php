<?php
declare(strict_types=1);

/**
 * Global authentication helper functions, unchanged from the original
 * includes/auth.php — kept as plain functions (Composer "files" autoload)
 * since they are used throughout view templates and controllers.
 */

if (!function_exists('isAuthenticated')) {
    function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }
}

if (!function_exists('currentUserId')) {
    function currentUserId(): ?int
    {
        return isAuthenticated() ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('currentUserName')) {
    function currentUserName(): string
    {
        return $_SESSION['user_name'] ?? '';
    }
}

if (!function_exists('currentUserEmail')) {
    function currentUserEmail(): string
    {
        return $_SESSION['user_email'] ?? '';
    }
}

if (!function_exists('currentUserRole')) {
    function currentUserRole(): string
    {
        return $_SESSION['user_role'] ?? 'guest';
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        if (!isAuthenticated()) {
            header('Location: /login.php');
            exit;
        }
    }
}

if (!function_exists('requireGuest')) {
    function requireGuest(): void
    {
        if (isAuthenticated()) {
            header('Location: /account-dashboard.php');
            exit;
        }
    }
}

if (!function_exists('currentAccountKind')) {
    function currentAccountKind(): string
    {
        return $_SESSION['account_kind'] ?? 'customer';
    }
}

if (!function_exists('isStaffAccount')) {
    function isStaffAccount(): bool
    {
        return isAuthenticated() && currentAccountKind() === 'staff';
    }
}
