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

if ($payload['exp'] < time()) {
    echo json_encode(['status' => 'error', 'message' => 'Token expired']);
    exit();
}

$user_id = $payload['user_id'];

try {
    // Profile
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, profile_picture, couple_name, wedding_date, venue, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }

    // Total orders - pastikan return INT
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE client_id = ?");
    $stmt->execute([$user_id]);
    $totalOrders = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending payments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE client_id = ? AND payment_status IN ('unpaid', 'waiting')");
    $stmt->execute([$user_id]);
    $pendingPayments = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as package_name 
        FROM orders o 
        JOIN packages p ON o.package_id = p.id 
        WHERE o.client_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentOrders as &$order) {
        $order['total_price_formatted'] = 'Rp ' . number_format($order['total_price'], 0, ',', '.');
        $order['wedding_date_formatted'] = date('d F Y', strtotime($order['wedding_date']));
        // Pastikan ID dalam bentuk string (karena Flutter model pakai String)
        $order['id'] = (string)$order['id'];
        $order['client_id'] = (string)$order['client_id'];
        $order['package_id'] = (string)$order['package_id'];
    }
    
    // Unread notifications
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unreadNotifications = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Wedding countdown
    $weddingCountdown = null;
    if ($profile['wedding_date']) {
        $wedding = new DateTime($profile['wedding_date']);
        $today = new DateTime();
        $diff = $today->diff($wedding);
        $weddingCountdown = $diff->days;
    }

    // Response
    echo json_encode([
        'status' => 'success',
        'message' => 'Dashboard data retrieved',
        'data' => [
            'profile' => $profile,
            'wedding_countdown' => $weddingCountdown,
            'recent_orders' => $recentOrders,
            'pending_payments' => $pendingPayments,      // int
            'unread_notifications' => $unreadNotifications, // int
            'total_orders' => $totalOrders              // int
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>