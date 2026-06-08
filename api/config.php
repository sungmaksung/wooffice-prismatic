<?php
// Bersihkan output buffer
if (ob_get_level()) ob_end_clean();
ob_start();

// Set CORS headers - HARUS PALING ATAS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting untuk debugging (matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Generate JWT Token
function generateToken($user_id, $full_name, $email, $role) {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'user_id' => $user_id,
        'name' => $full_name,
        'email' => $email,
        'role' => $role,
        'exp' => time() + (30 * 24 * 60 * 60)
    ]));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", 'prismatic_secret_key', true));
    return "$header.$payload.$signature";
}

function verifyToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $payload = json_decode(base64_decode($parts[1]), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;
    return $payload;
}

function authenticate() {
    $headers = getallheaders();
    $token = null;
    
    // Cek berbagai kemungkinan header Authorization
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    
    if (empty($token)) {
        sendResponse('error', 'Token required', null, 401);
    }
    
    $payload = verifyToken($token);
    if (!$payload) {
        sendResponse('error', 'Invalid or expired token', null, 401);
    }
    
    return $payload;
}

// Mulai session untuk bisa akses file web
function startClientSession($user_id) {
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_phone'] = $user['phone'];
        $_SESSION['user_role'] = 'client';
        $_SESSION['is_logged_in'] = true;
        return true;
    }
    return false;
}
?>