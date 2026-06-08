<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$full_name = $input['full_name'] ?? '';
$email = $input['email'] ?? '';
$phone = $input['phone'] ?? '';
$password = $input['password'] ?? '';
$couple_name = $input['couple_name'] ?? '';
$wedding_date = $input['wedding_date'] ?? '';
$venue = $input['venue'] ?? '';

if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
    sendResponse('error', 'Semua field wajib diisi', null, 400);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    sendResponse('error', 'Email sudah terdaftar', null, 409);
}

$hashed_password = md5($password);

$stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, status, couple_name, wedding_date, venue, created_at) VALUES (?, ?, ?, ?, 'client', 'pending', ?, ?, ?, NOW())");
$result = $stmt->execute([$full_name, $email, $phone, $hashed_password, $couple_name, $wedding_date, $venue]);

if ($result) {
    $user_id = $pdo->lastInsertId();
    
    // Notifikasi ke admin
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) SELECT id, '📝 Pendaftaran Client Baru', CONCAT(?, ' mendaftar, menunggu persetujuan'), 'info', 'admin/clients.php' FROM users WHERE role = 'admin'");
    $stmt->execute([$full_name]);
    
    sendResponse('success', 'Pendaftaran berhasil! Silakan tunggu persetujuan admin.', [
        'user_id' => $user_id,
        'status' => 'pending'
    ]);
} else {
    sendResponse('error', 'Gagal mendaftar', null, 500);
}
?>