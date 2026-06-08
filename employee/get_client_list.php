<?php
include '../config/database.php';

if(!isEmployee()) {
    echo json_encode(['clients' => []]);
    exit();
}

// Ambil semua client
$clients = $pdo->query("
    SELECT id, full_name, couple_name, email, phone, profile_picture, status 
    FROM users 
    WHERE role = 'client'
    ORDER BY id DESC
")->fetchAll();

// Hitung unread messages (pesan yang belum dibaca oleh employee)
foreach($clients as &$c) {
    // Pesan yang belum dibaca dari client ini (is_read = 0)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND is_read = 0 AND is_broadcast = 1");
    $stmt->execute([$c['id']]);
    $c['unread'] = (int)$stmt->fetchColumn();
    
    // Fallback untuk data kosong
    if(empty($c['profile_picture'])) {
        $c['profile_picture'] = '';
    }
    if(empty($c['couple_name'])) {
        $c['couple_name'] = $c['full_name'];
    }
}

echo json_encode(['clients' => $clients]);
?>