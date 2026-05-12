<?php
// ============================================================
//  CodeQuest — Database Configuration
//  File: config.php
//  Place this in: C:\xampp\htdocs\codequest\config.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // default XAMPP user
define('DB_PASS', '');           // default XAMPP password (empty)
define('DB_NAME', 'codequest');

// Session secret (change this to a random string in production)
define('SESSION_SECRET', 'cq_secret_change_me_2024');

// Create PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// JSON response helper
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Start session securely
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('codequest_sess');
        session_start();
    }
}

// Get logged-in user ID or null
function getSessionUserId(): ?int {
    startSession();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// Require authentication — returns user_id or sends 401
function requireAuth(): int {
    $uid = getSessionUserId();
    if ($uid === null) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    return $uid;
}

// CORS headers for XAMPP (same origin — not needed, but safe to include)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
