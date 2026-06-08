<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Ambil token dari berbagai sumber (SAMA PERSIS DENGAN GET_DASHBOARD)
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
    echo json_encode([
        'status' => 'error',
        'message' => 'Token required'
    ]);
    exit();
}

// Decode token
$payload = json_decode(base64_decode($token), true);

if (!$payload || !isset($payload['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid token format'
    ]);
    exit();
}

if ($payload['exp'] < time()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Token expired. Please login again.'
    ]);
    exit();
}

$user_id = $payload['user_id'];

try {
    // QUERY UNTUK GET_ORDERS (beda dengan get_dashboard)
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as package_name 
        FROM orders o 
        JOIN packages p ON o.package_id = p.id 
        WHERE o.client_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as &$order) {
        $order['total_price_formatted'] = 'Rp ' . number_format($order['total_price'], 0, ',', '.');
        $order['wedding_date_formatted'] = date('d F Y', strtotime($order['wedding_date']));
        
        // Cek payment status
        $stmt2 = $pdo->prepare("SELECT status FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt2->execute([$order['id']]);
        $payment = $stmt2->fetch(PDO::FETCH_ASSOC);
        $order['payment_status'] = $payment ? $payment['status'] : 'unpaid';
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Orders retrieved',
        'data' => $orders
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>