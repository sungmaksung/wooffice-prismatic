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
$headers = getallheaders();
$token = null;

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
$user_name = $payload['name'] ?? 'Client';

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_broadcast, created_at) VALUES (?, NULL, ?, 1, NOW())");
    $stmt->execute([$user_id, $message]);

    // Notify all employees
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) SELECT id, '💬 Pesan Baru dari Client', CONCAT('Dari ', ?, ': ', LEFT(?, 50)), 'chat', 'employee/cs.php' FROM users WHERE role = 'employee'");
    $stmt->execute([$user_name, $message]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Message sent'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>