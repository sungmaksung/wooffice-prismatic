<?php
include '../config/database.php';

if(!isEmployee()) {
    echo json_encode(['messages' => []]);
    exit();
}

$client_id = $_GET['client_id'] ?? 0;
$user_id = $_SESSION['user_id'];

if(!$client_id) {
    echo json_encode(['messages' => []]);
    exit();
}

// Mark messages from this client as read (untuk employee)
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND is_read = 0")->execute([$client_id]);

// Ambil semua pesan antara employee dan client ini
$messages = $pdo->prepare("
    SELECT 
        m.id,
        m.sender_id,
        m.message,
        m.is_read,
        m.is_broadcast,
        m.created_at,
        u.full_name,
        u.profile_picture,
        DATE_FORMAT(m.created_at, '%H:%i') as time
    FROM messages m 
    LEFT JOIN users u ON m.sender_id = u.id 
    WHERE m.sender_id = ? 
       OR (m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$messages->execute([$client_id, $client_id]);
$result = $messages->fetchAll();

echo json_encode(['messages' => $result]);
?>