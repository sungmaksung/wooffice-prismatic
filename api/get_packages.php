<?php
require_once 'config.php';

$stmt = $pdo->query("SELECT id, name, slug, price, description, features FROM packages WHERE is_custom = 0 ORDER BY price ASC");
$packages = $stmt->fetchAll();

foreach ($packages as &$pkg) {
    $pkg['price_formatted'] = 'Rp ' . number_format($pkg['price'], 0, ',', '.');
    if ($pkg['features']) {
        $pkg['features_list'] = explode("\n", $pkg['features']);
    } else {
        $pkg['features_list'] = [];
    }
}

sendResponse('success', 'Packages retrieved', $packages);
?>