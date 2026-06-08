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
$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID required']);
    exit();
}

try {
    // Get order data
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as package_name, p.description as package_description, p.features
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

    // Get verified payment
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'verified' LIMIT 1");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['status' => 'error', 'message' => 'Invoice not available. Payment not verified yet.']);
        exit();
    }

    // Get user profile
    $stmt = $pdo->prepare("SELECT full_name, email, phone, couple_name, wedding_date, venue FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $invoice = [
        'order_number' => $order['order_number'],
        'order_date' => $order['created_at'],
        'payment_date' => $payment['verified_at'],
        'package_name' => $order['package_name'],
        'package_description' => $order['package_description'],
        'wedding_date' => $order['wedding_date'],
        'venue' => $order['venue'],
        'guest_count' => $order['guest_count'],
        'total_price' => (int)$order['total_price'],
        'paid_amount' => (int)$payment['amount'],
        'method' => $payment['method'],
        'sender_name' => $payment['sender_name'],
        'proof_image' => $payment['proof_image'],
        'client_name' => $user['full_name'],
        'client_email' => $user['email'],
        'client_phone' => $user['phone'],
        'couple_name' => $user['couple_name']
    ];

    echo json_encode([
        'status' => 'success',
        'data' => $invoice
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>