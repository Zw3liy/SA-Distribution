<?php
declare(strict_types=1);

return [
    'name' => 'SA Business Distribution',
    'base_url' => '/',
    'meta' => [
        'title' => 'SA Business Distribution | Enterprise IT Solutions',
        'description' => 'SA Business Distribution delivers premium enterprise IT hardware, managed services, networking, security, and business automation solutions across South Africa.',
        'keywords' => 'enterprise IT, business solutions, managed IT, networking hardware, cybersecurity, office automation, servers, laptops',
        'locale' => 'en_ZA',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'sa_business',
        'user' => getenv('DB_USER') ?: 'sa_business_user',
        'pass' => getenv('DB_PASS') ?: 'change_this_securely',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'content_security_policy' => "default-src 'self'; img-src 'self' data: https:; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net;",
    ],
];
