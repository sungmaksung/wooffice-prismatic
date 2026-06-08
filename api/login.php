<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Email dan password wajib diisi'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'client'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Akun tidak ditemukan'
        ]);
        exit();
    }

    if (md5($password) !== $user['password']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Password salah'
        ]);
        exit();
    }

    if ($user['status'] !== 'active') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Akun belum aktif'
        ]);
        exit();
    }

    $token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['full_name'],
        'exp' => time() + (365 * 24 * 60 * 60)
    ]));

    unset($user['password']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Login berhasil',
        'data' => [
            'user' => $user,
            'token' => $token
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>