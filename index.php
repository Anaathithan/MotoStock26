<?php
// index.php
session_start();
require_once 'includes/config.php';

// If not logged in → show login
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

// If logged in → redirect based on role or go to dashboard
$role = $_SESSION['role'] ?? 'Employee';

header("Location: pages/dashboard.php");
exit;