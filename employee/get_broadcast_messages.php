<?php
include '../config/database.php';

if(!isEmployee()) {
    echo json_encode(['messages' => []]);
    exit();
}

// Ambil semua pesan broadcast
$messages = $pdo->query("
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
    WHERE m.is_broadcast = 1 
    ORDER BY m.created_at ASC
")->fetchAll();

echo json_encode(['messages' => $messages]);
?>