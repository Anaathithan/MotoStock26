<?php

define('DB_HOST','motostock26-motostock-26.b.aivencloud.com');
define('DB_USER','avnadmin');          
define('DB_PASS',getenv('DB_PASS') ?: '');              
define('DB_NAME','defaultdb');
define('DB_PORT',22213);

define('OWNER_EMAIL', 'anaathithan@gmail.com');
define('OWNER_SMTP_PASS', getenv('OWNER_SMTP_PASS') ?: '');

// 1. Initialize the connection
$conn = mysqli_init();

// 2. Tell PHP to expect an SSL connection (required by Aiven)
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

// 3. Connect securely using your defined constants and the SSL flag
mysqli_real_connect(
    $conn, 
    DB_HOST, 
    DB_USER, 
    DB_PASS, 
    DB_NAME, 
    DB_PORT, 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (mysqli_connect_errno()) {
    die("<h3>Our free-tier database is currently waking up! Please refresh this page in 30 seconds.</h3>");
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
?>