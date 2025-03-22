<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'used_parts_hub');

// Site configuration
define('SITE_NAME', 'UsedPartsHub');
define('SITE_URL', 'http://localhost/usedpartshub');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/usedpartshub/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Session configuration
session_start();

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Helper functions
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'You must be logged in to access this page';
        redirect(SITE_URL . '/login.php');
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function displayError($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

function displaySuccess($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}
?>