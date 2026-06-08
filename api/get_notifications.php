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
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function timeAgo($timestamp) {
        $diff = time() - strtotime($timestamp);
        if ($diff < 60) return $diff . " detik lalu";
        if ($diff < 3600) return floor($diff / 60) . " menit lalu";
        if ($diff < 86400) return floor($diff / 3600) . " jam lalu";
        return floor($diff / 86400) . " hari lalu";
    }

    foreach ($notifications as &$notif) {
        $notif['time_ago'] = timeAgo($notif['created_at']);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $notifications
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>