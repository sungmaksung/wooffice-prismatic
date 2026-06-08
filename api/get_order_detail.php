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
$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as package_name 
        FROM orders o 
        JOIN packages p ON o.package_id = p.id 
        WHERE o.id = ? AND o.client_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit();
    }

    $order['total_price_formatted'] = 'Rp ' . number_format($order['total_price'], 0, ',', '.');
    $order['wedding_date_formatted'] = date('d F Y', strtotime($order['wedding_date']));

    echo json_encode([
        'status' => 'success',
        'data' => $order
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>