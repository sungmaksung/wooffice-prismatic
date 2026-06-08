<?php
include '../config/database.php';

if(!isClient()) {
    echo json_encode(['messages' => []]);
    exit();
}

$user_id = $_SESSION['user_id'];

$messages = $pdo->prepare("
    SELECT m.*, u.full_name, u.profile_picture,
           DATE_FORMAT(m.created_at, '%H:%i') as time
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE (m.is_broadcast = 1 AND m.receiver_id IS NULL)
       OR (m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$messages->execute([$user_id]);
$messages = $messages->fetchAll();

echo json_encode(['messages' => $messages]);
?>