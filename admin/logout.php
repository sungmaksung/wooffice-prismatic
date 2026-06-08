<?php
session_start();
require_once '../config/database.php';

if(isset($_SESSION['admin_id'])) {
    // Catat logout ke user_sessions
    $stmt = $pdo->prepare("UPDATE user_sessions SET logout_time = NOW(), session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW()) WHERE user_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
    $stmt->execute([$_SESSION['admin_id']]);
    
    // Catat ke system_logs
    $stmt = $pdo->prepare("INSERT INTO system_logs (log_type, message, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute(['logout', "Admin {$_SESSION['admin_name']} logout", "ID: {$_SESSION['admin_id']}", $_SERVER['REMOTE_ADDR']]);
}

session_destroy();
header('Location: login.php');
exit();
?>