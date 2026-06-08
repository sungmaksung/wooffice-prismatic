<?php
function logEmployeeActivity($pdo, $action, $action_type, $target_type = null, $target_id = null, $details = null) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') return;
    
    $employee_id = $_SESSION['user_id'];
    $employee_name = $_SESSION['user_name'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO employee_activities (employee_id, employee_name, action, action_type, target_type, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employee_id, $employee_name, $action, $action_type, $target_type, $target_id, $details, $ip_address, $user_agent]);
}

// Panggil di awal setiap file employee untuk mencatat akses halaman
if (basename($_SERVER['PHP_SELF']) !== 'login.php' && !isset($_SESSION['activity_logged_' . basename($_SERVER['PHP_SELF'])])) {
    logEmployeeActivity($pdo, 'Mengakses halaman ' . basename($_SERVER['PHP_SELF']), 'view', 'page', null, null);
    $_SESSION['activity_logged_' . basename($_SERVER['PHP_SELF'])] = true;
}
?>