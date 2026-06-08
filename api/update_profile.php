<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Ambil token dari header
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth);

if (empty($token)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Token required'
    ]);
    exit();
}

// Decode token
$payload = json_decode(base64_decode($token), true);
if (!$payload || !isset($payload['user_id']) || $payload['exp'] < time()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired token'
    ]);
    exit();
}

$user_id = $payload['user_id'];

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);

$full_name = $input['full_name'] ?? null;
$phone = $input['phone'] ?? null;
$couple_name = $input['couple_name'] ?? null;
$wedding_date = $input['wedding_date'] ?? null;
$venue = $input['venue'] ?? null;

$updates = [];
$params = [];

if ($full_name) {
    $updates[] = "full_name = ?";
    $params[] = $full_name;
}
if ($phone) {
    $updates[] = "phone = ?";
    $params[] = $phone;
}
if ($couple_name) {
    $updates[] = "couple_name = ?";
    $params[] = $couple_name;
}
if ($wedding_date) {
    $updates[] = "wedding_date = ?";
    $params[] = $wedding_date;
}
if ($venue) {
    $updates[] = "venue = ?";
    $params[] = $venue;
}

if (empty($updates)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Tidak ada data yang diupdate'
    ]);
    exit();
}

$params[] = $user_id;
$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode([
    'status' => 'success',
    'message' => 'Profile berhasil diupdate'
]);
?>