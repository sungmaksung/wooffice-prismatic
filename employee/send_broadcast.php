<?php
include '../config/database.php';

if(!isEmployee()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in as employee']);
    exit();
}

$message = $_POST['message'] ?? '';
$user_id = $_SESSION['user_id'];

if(trim($message) == '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

$stmt = $pdo->prepare("INSERT INTO messages (sender_id, message, is_broadcast, receiver_id, is_read) VALUES (?, ?, 1, NULL, 0)");
$result = $stmt->execute([$user_id, $message]);

if($result) {
    // Notify all active clients
    $clients = $pdo->query("SELECT id FROM users WHERE role = 'client' AND status = 'active'")->fetchAll();
    foreach($clients as $client) {
        addNotification($client['id'], '📢 Pengumuman dari CS', substr($message, 0, 100), 'chat', 'client/chat.php');
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>