<?php
/**
 * Strathosphere - Database Configuration
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'strathosphere');
define('BASE_URL', 'http://localhost/strathosphere-prod/');

session_start();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Authentication required', 'code' => 401]);
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Admin access required', 'code' => 403]);
    }
}

function sanitize($conn, $input) {
    return htmlspecialchars(strip_tags($conn->real_escape_string($input)), ENT_QUOTES, 'UTF-8');
}