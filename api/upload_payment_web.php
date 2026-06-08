<?php
// Hapus semua output buffer yang mungkin ada
ob_clean();

// Set headers untuk CORS - harus sebelum output apapun
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
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

$order_id = $_POST['order_id'] ?? null;
$method = $_POST['method'] ?? null;
$sender_name = $_POST['sender_name'] ?? '';

if (!$order_id || !$method || !isset($_FILES['proof_image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID, method, and payment proof required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT o.* FROM orders o WHERE o.id = ? AND o.client_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit();
    }

    // Buat folder jika belum ada
    $upload_dir = __DIR__ . '/../uploads/payments/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $extension = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    $filename = 'payment_' . $order_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $filepath)) {
        $proof_path = 'uploads/payments/' . $filename;

        $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, method, proof_image, sender_name, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$order_id, $order['total_price'], $method, $proof_path, $sender_name]);

        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'waiting' WHERE id = ?");
        $stmt->execute([$order_id]);

        // Notifikasi ke employee
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) SELECT id, '💳 Pembayaran Baru', CONCAT('Client melakukan pembayaran untuk order #', ?), 'payment', 'employee/payment_requests.php' FROM users WHERE role = 'employee'");
        $stmt->execute([$order['order_number']]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Bukti pembayaran berhasil diupload',
            'data' => [
                'status' => 'pending',
                'proof_image' => $proof_path
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal upload bukti pembayaran']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>