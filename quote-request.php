<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';

$appConfig = require __DIR__ . '/config/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productSlug = trim((string) filter_input(INPUT_POST, 'product_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    $productName = trim((string) filter_input(INPUT_POST, 'product_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    $name = trim((string) filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    $company = trim((string) filter_input(INPUT_POST, 'company', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
    $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    $message = trim((string) filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

    if ($name !== '' && $email !== '' && $productSlug !== '') {
        $_SESSION['quote_requests'][] = [
            'product_slug' => $productSlug,
            'product_name' => $productName,
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        setFlashMessage('Quote request sent successfully. Our team will contact you shortly.');
    } else {
        setFlashMessage('Please provide your name, email, and product details.');
    }

    header('Location: product-details.php?slug=' . urlencode($productSlug));
    exit;
}

header('Location: products.php');
exit;
