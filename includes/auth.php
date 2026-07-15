<?php
declare(strict_types=1);

function isAuthenticated(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return isAuthenticated() ? (int) $_SESSION['user_id'] : null;
}

function currentUserName(): string
{
    return $_SESSION['user_name'] ?? '';
}

function currentUserEmail(): string
{
    return $_SESSION['user_email'] ?? '';
}

function currentUserRole(): string
{
    return $_SESSION['user_role'] ?? 'guest';
}

function requireLogin(): void
{
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function requireGuest(): void
{
    if (isAuthenticated()) {
        header('Location: account-dashboard.php');
        exit;
    }
}
