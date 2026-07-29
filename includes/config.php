<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          
define('DB_PASS', '');              
define('DB_NAME', 'motostock26');

define('OWNER_EMAIL', 'tharikloveskarina@gmail.com');
define('OWNER_SMTP_PASS', getenv('OWNER_SMTP_PASS') ?: '');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
