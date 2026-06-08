<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Ambil token
$token = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $token = str_replace('Bearer ', '', $headers['authorization']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
}

if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Token required']);
    exit();
}

// Decode token
$payload = json_decode(base64_decode($token), true);

if (!$payload || !isset($payload['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit();
}

$user_id = $payload['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Current password and new password required']);
    exit();
}

if (strlen($new_password) < 4) {
    echo json_encode(['status' => 'error', 'message' => 'Password minimal 4 karakter']);
    exit();
}

try {
    // Get current user password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }
    
    // Verify current password
    if (md5($current_password) !== $user['password']) {
        echo json_encode(['status' => 'error', 'message' => 'Password saat ini salah']);
        exit();
    }
    
    // Update password
    $new_hashed_password = md5($new_password);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_hashed_password, $user_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Password berhasil diubah'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>