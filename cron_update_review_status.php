<?php
include 'config/database.php';

// Update orders that have wedding date passed (1 day after wedding) to can_review = 1
$pdo->query("
    UPDATE orders 
    SET can_review = 1 
    WHERE wedding_date < DATE_SUB(NOW(), INTERVAL 1 DAY)
      AND status = 'approved'
      AND can_review = 0
");

echo "✅ " . $pdo->query("SELECT ROW_COUNT()")->fetchColumn() . " orders updated to can_review = 1";
?>