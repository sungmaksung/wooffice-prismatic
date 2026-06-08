<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

if ($package_id == 0) {
    header('Location: packages.php');
    exit();
}

$package = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$package->execute([$package_id]);
$package = $package->fetch();

if (!$package) {
    header('Location: packages.php');
    exit();
}

$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$options = [];
if ($package['is_custom'] || $package['slug'] == 'paket_custom') {
    $options['catering'] = $pdo->query("SELECT * FROM package_options WHERE category = 'catering'")->fetchAll();
    $options['decoration'] = $pdo->query("SELECT * FROM package_options WHERE category = 'decoration'")->fetchAll();
    $options['documentation'] = $pdo->query("SELECT * FROM package_options WHERE category = 'documentation'")->fetchAll();
    $options['entertainment'] = $pdo->query("SELECT * FROM package_options WHERE category = 'entertainment'")->fetchAll();
    $options['makeup'] = $pdo->query("SELECT * FROM package_options WHERE category = 'makeup'")->fetchAll();
    $options['wedding_dress'] = $pdo->query("SELECT * FROM package_options WHERE category = 'wedding_dress'")->fetchAll();
    $options['venue'] = $pdo->query("SELECT * FROM package_options WHERE category = 'venue'")->fetchAll();
    $options['addons'] = $pdo->query("SELECT * FROM package_options WHERE category = 'addons'")->fetchAll();
}

$total_price = $package['price'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wedding_date = $_POST['wedding_date'];
    $venue = $_POST['venue'];
    $guest_count = $_POST['guest_count'];
    $notes = $_POST['notes'];
    $custom_options = null;
    $custom_price = 0;
    $selected_venue_id = $_POST['venue_id'] ?? 0;
    $selected_addons = [];
    
    if ($package['slug'] == 'paket_custom') {
        $selected = [];
        $selected['catering'] = $_POST['catering'] ?? 0;
        $selected['decoration'] = $_POST['decoration'] ?? 0;
        $selected['documentation'] = $_POST['documentation'] ?? 0;
        $selected['entertainment'] = $_POST['entertainment'] ?? 0;
        $selected['makeup'] = $_POST['makeup'] ?? 0;
        $selected['wedding_dress'] = $_POST['wedding_dress'] ?? 0;
        $selected['venue'] = $selected_venue_id;
        
        if(isset($_POST['addons']) && is_array($_POST['addons'])) {
            $selected_addons = $_POST['addons'];
            $selected['addons'] = $selected_addons;
        }
        
        foreach($selected as $cat => $opt_id) {
            if($opt_id && $cat != 'addons') {
                if(is_array($opt_id)) continue;
                $opt = $pdo->prepare("SELECT price FROM package_options WHERE id = ?");
                $opt->execute([$opt_id]);
                $opt_price = $opt->fetchColumn();
                $custom_price += $opt_price;
            }
        }
        
        // Add addons prices
        foreach($selected_addons as $addon_id) {
            $opt = $pdo->prepare("SELECT price FROM package_options WHERE id = ?");
            $opt->execute([$addon_id]);
            $custom_price += $opt->fetchColumn();
        }
        
        $custom_options = json_encode($selected);
        $total_price = $custom_price;
    }
    
    $order_number = 'PO-' . date('Ymd') . '-' . rand(100, 999);
    
    $stmt = $pdo->prepare("INSERT INTO orders (order_number, client_id, package_id, custom_options, custom_price, total_price, wedding_date, venue, guest_count, notes, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$order_number, $_SESSION['user_id'], $package_id, $custom_options, $custom_price, $total_price, $wedding_date, $venue, $guest_count, $notes]);
    
    $order_id = $pdo->lastInsertId();
    
    $employees = $pdo->query("SELECT id FROM users WHERE role = 'employee'")->fetchAll();
    foreach($employees as $emp) {
        addNotification($emp['id'], '📦 Pesanan Baru', $_SESSION['user_name'] . ' memesan paket ' . $package['name'], 'order', 'employee/payment_requests.php');
    }
    
    header("Location: orders.php?order_id=$order_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesan Paket - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
        input, select, textarea { background: #1A1A1A; border-color: #2A2A2A; color: white; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #FFD700; ring: 2px solid rgba(255,215,0,0.2); }
        label { color: #D1D5DB; }
    </style>
    <script>
        function updateTotal() {
            <?php if($package['slug'] == 'paket_custom'): ?>
            let total = 0;
            const selects = ['catering', 'decoration', 'documentation', 'entertainment', 'makeup', 'wedding_dress', 'venue'];
            selects.forEach(cat => {
                const select = document.getElementById(cat);
                if(select && select.value) {
                    total += parseInt(select.options[select.selectedIndex].getAttribute('data-price') || 0);
                }
            });
            
            // Add addons
            const addonCheckboxes = document.querySelectorAll('input[name="addons[]"]:checked');
            addonCheckboxes.forEach(cb => {
                total += parseInt(cb.getAttribute('data-price') || 0);
            });
            
            document.getElementById('total_display').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
            document.getElementById('total_price').value = total;
            <?php endif; ?>
        }
        
        function toggleMobileSidebar() {
            document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const addons = document.querySelectorAll('input[name="addons[]"]');
            addons.forEach(cb => {
                cb.addEventListener('change', updateTotal);
            });
            <?php if($package['slug'] == 'paket_custom'): ?>
            updateTotal();
            <?php endif; ?>
        });
    </script>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="max-w-4xl mx-auto">
        <a href="packages.php" class="text-[#FFD700] mb-4 inline-block hover:underline">← Kembali ke Daftar Paket</a>
        
        <div class="bg-[#1A1A1A] rounded-2xl border border-[#2A2A2A] overflow-hidden">
            <div class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] p-6">
                <h1 class="font-serif text-2xl font-semibold text-[#0A0A0A]">Pesan Paket: <?= $package['name'] ?></h1>
                <p class="text-[#0A0A0A]/70 text-sm">Isi data berikut untuk memesan paket pernikahan</p>
            </div>
            
            <div class="p-6">
                <form method="POST" id="orderForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[#D1D5DB] mb-1">Tanggal Pernikahan</label>
                            <input type="date" name="wedding_date" required value="<?= $user['wedding_date'] ?>" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                        </div>
                        <div>
                            <label class="block text-[#D1D5DB] mb-1">Lokasi Gedung</label>
                            <input type="text" name="venue" required value="<?= $user['venue'] ?>" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                        </div>
                        <div>
                            <label class="block text-[#D1D5DB] mb-1">Jumlah Tamu</label>
                            <input type="number" name="guest_count" value="100" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                        </div>
                        <div>
                            <label class="block text-[#D1D5DB] mb-1">No HP (Otomatis)</label>
                            <input type="text" value="<?= $_SESSION['user_phone'] ?>" disabled class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#252525] text-[#9CA3AF]">
                        </div>
                    </div>
                    
                    <?php if($package['slug'] == 'paket_custom'): ?>
                    <div class="mt-8 border-t border-[#2A2A2A] pt-6">
                        <h2 class="font-serif text-xl font-semibold text-[#FFD700] mb-4">✨ Custom Paketmu ✨</h2>
                        
                        <!-- Venue Selection -->
                        <div class="mb-5">
                            <label class="block text-[#D1D5DB] mb-2">📍 Pilih Venue</label>
                            <select id="venue" name="venue_id" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                <option value="0">Pilih venue...</option>
                                <?php foreach($options['venue'] as $opt): ?>
                                <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[#D1D5DB] mb-2">🍽️ Catering</label>
                                <select id="catering" name="catering" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                    <option value="0">Pilih catering...</option>
                                    <?php foreach($options['catering'] as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[#D1D5DB] mb-2">🎨 Dekorasi</label>
                                <select id="decoration" name="decoration" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                    <option value="0">Pilih dekorasi...</option>
                                    <?php foreach($options['decoration'] as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[#D1D5DB] mb-2">📸 Dokumentasi</label>
                                <select id="documentation" name="documentation" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                    <option value="0">Pilih dokumentasi...</option>
                                    <?php foreach($options['documentation'] as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[#D1D5DB] mb-2">🎵 Entertainment</label>
                                <select id="entertainment" name="entertainment" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                    <option value="0">Pilih entertainment...</option>
                                    <?php foreach($options['entertainment'] as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[#D1D5DB] mb-2">💄 Makeup</label>
                                <select id="makeup" name="makeup" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                    <option value="0">Pilih makeup...</option>
                                    <?php foreach($options['makeup'] as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[#D1D5DB] mb-2">👰 Gaun Pengantin</label>
                                <select id="wedding_dress" name="wedding_dress" onchange="updateTotal()" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white">
                                    <option value="0">Pilih gaun...</option>
                                    <?php foreach($options['wedding_dress'] as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>"><?= $opt['name'] ?> - Rp <?= number_format($opt['price'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Add-ons Section -->
                        <div class="mt-6">
                            <label class="block text-[#D1D5DB] mb-2">✨ Tambahan Opsional (Add-ons)</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php foreach($options['addons'] as $opt): ?>
                                <label class="flex items-center gap-3 p-3 bg-[#1A1A1A] rounded-xl border border-[#2A2A2A] cursor-pointer hover:border-[#FFD700]/50 transition">
                                    <input type="checkbox" name="addons[]" value="<?= $opt['id'] ?>" data-price="<?= $opt['price'] ?>" onchange="updateTotal()" class="w-4 h-4 rounded border-gray-600 bg-[#252525] text-[#FFD700] focus:ring-[#FFD700]">
                                    <div class="flex-1">
                                        <span class="text-white text-sm"><?= $opt['name'] ?></span>
                                        <span class="text-[#FFD700] text-sm ml-2">+Rp <?= number_format($opt['price'], 0, ',', '.') ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mt-6 p-4 bg-[#252525] rounded-xl border border-[#2A2A2A]">
                            <p class="text-lg font-bold text-[#FFD700]">Total Harga: <span id="total_display">Rp 0</span></p>
                            <input type="hidden" id="total_price" name="total_price" value="0">
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-6 p-4 bg-[#252525] rounded-xl border border-[#2A2A2A]">
                        <p class="text-lg font-bold text-[#FFD700]">Total Harga: Rp <?= number_format($package['price'], 0, ',', '.') ?></p>
                        <input type="hidden" name="total_price" value="<?= $package['price'] ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-6">
                        <label class="block text-[#D1D5DB] mb-1">Catatan Tambahan</label>
                        <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#1A1A1A] text-white placeholder:text-[#6B6B6B]" placeholder="Ada request khusus? tulis di sini..."></textarea>
                    </div>
                    
                    <button type="submit" class="w-full mt-6 bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-3 rounded-xl hover:shadow-lg hover:scale-[1.02] transition-all">
                        Lanjut ke Pembayaran →
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    <?php if($package['slug'] == 'paket_custom'): ?>
    updateTotal();
    <?php endif; ?>
</script>
</body>
</html>