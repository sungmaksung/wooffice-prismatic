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

// Ambil token (SAMA PERSIS)
$token = null;

$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $token = str_replace('Bearer ', '', $headers['authorization']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
}

if (empty($token) && isset($_GET['token'])) {
    $token = $_GET['token'];
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
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, profile_picture, couple_name, wedding_date, venue, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $user
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>