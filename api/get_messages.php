<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    $stmt = $pdo->prepare("
        (SELECT m.*, 'client' as sender_role, u.full_name as sender_name 
         FROM messages m 
         JOIN users u ON m.sender_id = u.id 
         WHERE m.sender_id = ? AND m.is_broadcast = 1)
        UNION ALL
        (SELECT m.*, 'employee' as sender_role, u.full_name as sender_name 
         FROM messages m 
         JOIN users u ON m.sender_id = u.id 
         WHERE m.receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $messages
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>