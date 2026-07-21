<?php
declare(strict_types=1);

/**
 * Admin portal navigation shell. Included only from views/pages/admin/*
 * -- the storefront's components/header.php is never used on admin
 * pages, since the admin portal is a distinct staff-only surface
 * (docs/specs/02-administration.md §13).
 */
?>
<header class="site-header admin-header">
    <div class="container header-inner">
        <a class="brand" href="/admin">Admin — <?= esc($appConfig['name']); ?></a>
        <nav class="site-nav admin-nav" aria-label="Admin navigation">
            <a href="/admin">Dashboard</a>
            <a href="/admin/catalog/products">Catalog</a>
            <a href="/admin/customers">Customers</a>
            <a href="/admin/inventory">Inventory</a>
            <a href="/admin/staff">Staff</a>
            <a href="/admin/settings">Settings</a>
            <a href="/admin/feature-flags">Feature Flags</a>
            <a href="/admin/audit-log">Audit Log</a>
            <a