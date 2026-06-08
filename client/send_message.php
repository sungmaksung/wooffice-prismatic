<?php
include '../config/database.php';

// Cek login
if(!isClient()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$message = $_POST['message'] ?? '';
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

if(trim($message) == '') {
    echo json_encode(['success' => false, 'error' => 'Pesan kosong']);
    exit();
}

// Simpan pesan client (broadcast ke semua employee)
$stmt = $pdo->prepare("INSERT INTO messages (sender_id, message, is_broadcast, receiver_id, is_read) VALUES (?, ?, 1, NULL, 0)");
$result = $stmt->execute([$user_id, $message]);

if($result) {
    // Notifikasi ke semua employee
    $employees = $pdo->query("SELECT id FROM users WHERE role = 'employee' AND status = 'active'")->fetchAll();
    foreach($employees as $emp) {
        addNotification($emp['id'], '💬 Pesan Baru dari Client', $user_name . ': ' . substr($message, 0, 50), 'chat', 'employee/cs.php');
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan pesan']);
}
?>