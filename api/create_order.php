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

// Ambil token dari berbagai sumber (SAMA PERSIS dengan get_orders.php)
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
        'message' => 'Invalid token'
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

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);

$package_id = $input['package_id'] ?? null;
$wedding_date = $input['wedding_date'] ?? null;
$venue = $input['venue'] ?? '';
$guest_count = $input['guest_count'] ?? 100;
$notes = $input['notes'] ?? '';

if (!$package_id || !$wedding_date || !$venue) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Package ID, wedding date, and venue required'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT price, name FROM packages WHERE id = ?");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Package not found'
        ]);
        exit();
    }

    $order_number = 'PO-' . date('Ymd') . '-' . rand(100, 999);

    $stmt = $pdo->prepare("INSERT INTO orders (order_number, client_id, package_id, total_price, wedding_date, venue, guest_count, notes, status, payment_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', NOW())");
    $stmt->execute([$order_number, $user_id, $package_id, $package['price'], $wedding_date, $venue, $guest_count, $notes]);

    $order_id = $pdo->lastInsertId();

    // Notifikasi ke employee
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) SELECT id, '📦 Pesanan Baru', CONCAT('Client memesan paket ', ?, ' #', ?), 'order', 'employee/payment_requests.php' FROM users WHERE role = 'employee'");
    $stmt->execute([$package['name'], $order_number]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Order created successfully',
        'data' => [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total_price' => $package['price'],
            'total_price_formatted' => 'Rp ' . number_format($package['price'], 0, ',', '.')
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>