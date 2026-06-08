<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle update general settings
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general'])) {
    $company_name = $_POST['company_name'];
    $company_tagline = $_POST['company_tagline'];
    $company_email = $_POST['company_email'];
    $company_phone = $_POST['company_phone'];
    $company_address = $_POST['company_address'];
    $company_instagram = $_POST['company_instagram'];
    $company_whatsapp = $_POST['company_whatsapp'];
    
    $settings = [
        'company_name' => $company_name,
        'company_tagline' => $company_tagline,
        'company_email' => $company_email,
        'company_phone' => $company_phone,
        'company_address' => $company_address,
        'company_instagram' => $company_instagram,
        'company_whatsapp' => $company_whatsapp,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents('../config/settings.json', json_encode($settings));
    $message = 'Pengaturan berhasil disimpan!';
}

// Handle clear cache
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    try {
        $deleted_count = 0;
        
        $temp_dirs = [
            '../temp/',
            '../cache/',
            '../uploads/temp/',
            '../uploads/forum/temp/',
            sys_get_temp_dir() . '/prismatic_'
        ];
        
        foreach($temp_dirs as $dir) {
            if(file_exists($dir) && is_dir($dir)) {
                $files = glob($dir . '*');
                foreach($files as $file) {
                    if(is_file($file)) {
                        unlink($file);
                        $deleted_count++;
                    }
                }
            }
        }
        
        // Clear any PHP opcache
        if(function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        $message = "🧹 Cache berhasil dibersihkan! ($deleted_count file terhapus)";
    } catch(Exception $e) {
        $error = "Gagal membersihkan cache: " . $e->getMessage();
    }
}

// Handle delete all logs
if(isset($_GET['delete_all_logs']) && $_GET['delete_all_logs'] == 'confirm') {
    try {
        // List semua tabel log yang ada di database
        $log_tables = [
            'backup_logs',
            'employee_activities',
            'login_attempts',
            'system_logs',
            'user_sessions'
        ];
        
        // Cari tabel lain yang mengandung kata 'log' untuk berjaga-jaga
        $stmt = $pdo->query("SHOW TABLES LIKE '%log%'");
        while($row = $stmt->fetch(PDO::FETCH_NUM)) {
            if(!in_array($row[0], $log_tables) && $row[0] !== 'forum_likes') {
                $log_tables[] = $row[0];
            }
        }
        
        $deleted_counts = [];
        foreach($log_tables as $table) {
            // Cek apakah tabel benar-benar ada
            $check = $pdo->query("SHOW TABLES LIKE '$table'");
            if($check->rowCount() > 0) {
                $delete_stmt = $pdo->prepare("DELETE FROM `$table`");
                $delete_stmt->execute();
                $deleted_counts[$table] = $delete_stmt->rowCount();
                
                // Reset AUTO_INCREMENT
                $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            }
        }
        
        // Buat pesan sukses
        $summary = [];
        foreach($deleted_counts as $table => $count) {
            if($count > 0) {
                $summary[] = "$table: $count record";
            }
        }
        
        if(empty($summary)) {
            $message = "✅ Tidak ada data logs yang ditemukan untuk dihapus.";
        } else {
            $message = "🗑️ Berhasil menghapus semua logs: " . implode(', ', $summary);
        }
        
        // Catat aksi ke system_logs
        try {
            $log_stmt = $pdo->prepare("INSERT INTO system_logs (log_type, message, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute(['admin_action', 'Menghapus semua logs', json_encode($deleted_counts), $_SERVER['REMOTE_ADDR']]);
        } catch(Exception $e) {
            // Ignore logging error
        }
        
    } catch(Exception $e) {
        $error = "❌ Gagal menghapus logs: " . $e->getMessage();
    }
    
    header("Location: settings.php?msg=" . urlencode($message) . ($error ? "&error=" . urlencode($error) : ""));
    exit();
}

// Handle delete old logs (keep last 30 days)
if(isset($_GET['delete_old_logs']) && $_GET['delete_old_logs'] == 'confirm') {
    try {
        $log_tables = [
            'backup_logs',
            'employee_activities',
            'system_logs'
        ];
        
        $deleted_counts = [];
        
        // Backup logs
        $delete_stmt = $pdo->prepare("DELETE FROM backup_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $delete_stmt->execute();
        if($delete_stmt->rowCount() > 0) {
            $deleted_counts['backup_logs'] = $delete_stmt->rowCount();
        }
        
        // Employee activities
        $delete_stmt = $pdo->prepare("DELETE FROM employee_activities WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $delete_stmt->execute();
        if($delete_stmt->rowCount() > 0) {
            $deleted_counts['employee_activities'] = $delete_stmt->rowCount();
        }
        
        // System logs
        $delete_stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $delete_stmt->execute();
        if($delete_stmt->rowCount() > 0) {
            $deleted_counts['system_logs'] = $delete_stmt->rowCount();
        }
        
        // Login attempts (using attempt_time column)
        $delete_stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $delete_stmt->execute();
        if($delete_stmt->rowCount() > 0) {
            $deleted_counts['login_attempts'] = $delete_stmt->rowCount();
        }
        
        // User sessions (only those that have logout_time)
        $delete_stmt = $pdo->prepare("DELETE FROM user_sessions WHERE logout_time IS NOT NULL AND logout_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $delete_stmt->execute();
        if($delete_stmt->rowCount() > 0) {
            $deleted_counts['user_sessions'] = $delete_stmt->rowCount();
        }
        
        // Optional: Delete old notifications
        $delete_stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $delete_stmt->execute();
        if($delete_stmt->rowCount() > 0) {
            $deleted_counts['notifications'] = $delete_stmt->rowCount();
        }
        
        $summary = [];
        foreach($deleted_counts as $table => $count) {
            $summary[] = "$table: $count record";
        }
        
        if(empty($summary)) {
            $message = "✅ Tidak ada logs lama (>30 hari) yang ditemukan.";
        } else {
            $message = "🗑️ Logs lama (>30 hari) berhasil dihapus! " . implode(', ', $summary);
        }
        
    } catch(Exception $e) {
        $error = "❌ Gagal menghapus logs lama: " . $e->getMessage();
    }
    
    header("Location: settings.php?msg=" . urlencode($message) . ($error ? "&error=" . urlencode($error) : ""));
    exit();
}

// Load settings
$settings = [];
if(file_exists('../config/settings.json')) {
    $settings = json_decode(file_get_contents('../config/settings.json'), true);
}

// Default values
$defaults = [
    'company_name' => 'Prismatic Organizer',
    'company_tagline' => 'Truly Fantastic',
    'company_email' => 'hello@prismatic-organizer.com',
    'company_phone' => '+62 822-1907-4421',
    'company_address' => 'Cimasuk Residence, Blok G-5, Daerah Suci, Karangpawitan, Garut',
    'company_instagram' => 'prismatic_eo_wo',
    'company_whatsapp' => '6282219074421'
];

// Maintenance mode toggle
if(isset($_GET['maintenance'])) {
    $maintenance = $_GET['maintenance'] === 'on' ? 'on' : 'off';
    
    // Save to JSON
    file_put_contents('../config/maintenance.json', json_encode(['status' => $maintenance, 'updated_at' => date('Y-m-d H:i:s')]));
    
    // Create/delete flag file for .htaccess
    $flagFile = '../config/maintenance.flag';
    if($maintenance === 'on') {
        file_put_contents($flagFile, date('Y-m-d H:i:s'));
    } else {
        if(file_exists($flagFile)) unlink($flagFile);
    }
    
    $message = 'Maintenance mode ' . ($maintenance == 'on' ? 'diaktifkan' : 'dinonaktifkan');
    header("Location: settings.php?msg=" . urlencode($message));
    exit();
}

$maintenance_status = 'off';
if(file_exists('../config/maintenance.json')) {
    $maintenance_data = json_decode(file_get_contents('../config/maintenance.json'), true);
    $maintenance_status = $maintenance_data['status'] ?? 'off';
}

$msg = $_GET['msg'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Get log statistics from actual database
$log_stats = [];
try {
    $log_tables = ['backup_logs', 'employee_activities', 'login_attempts', 'system_logs', 'user_sessions'];
    foreach($log_tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if($check->rowCount() > 0) {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $log_stats[$table] = $count_stmt->fetchColumn();
        }
    }
} catch(PDOException $e) {
    $log_stats = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan Sistem - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(139, 92, 246, 0.2); }
        .setting-card { background: #1E293B; border: 1px solid #334155; transition: all 0.3s; }
        .setting-card:hover { border-color: #8B5CF6; }
        .danger-card { border-color: #EF4444; }
        .danger-card:hover { border-color: #DC2626; background: rgba(239, 68, 68, 0.05); }
        .admin-main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">⚙️ Pengaturan Sistem</h1>
        <p class="text-[#94A3B8] mt-1">Kelola konfigurasi sistem Prismatic Organizer</p>
    </div>
    
    <?php if($msg): ?>
    <div class="mb-4 p-4 bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-500/30 text-green-400 rounded-xl flex items-center gap-3">
        <span class="text-2xl">✅</span>
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if($error_msg): ?>
    <div class="mb-4 p-4 bg-gradient-to-r from-red-500/20 to-rose-500/20 border border-red-500/30 text-red-400 rounded-xl flex items-center gap-3">
        <span class="text-2xl">❌</span>
        <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- General Settings -->
        <div class="lg:col-span-2">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> Pengaturan Umum
                </h2>
                <form method="POST">
                    <input type="hidden" name="update_general" value="1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-300 mb-1">Nama Perusahaan</label>
                            <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? $defaults['company_name']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Tagline</label>
                            <input type="text" name="company_tagline" value="<?= htmlspecialchars($settings['company_tagline'] ?? $defaults['company_tagline']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Email Perusahaan</label>
                            <input type="email" name="company_email" value="<?= htmlspecialchars($settings['company_email'] ?? $defaults['company_email']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">No Telepon</label>
                            <input type="text" name="company_phone" value="<?= htmlspecialchars($settings['company_phone'] ?? $defaults['company_phone']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Instagram</label>
                            <input type="text" name="company_instagram" value="<?= htmlspecialchars($settings['company_instagram'] ?? $defaults['company_instagram']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition" placeholder="@username">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">WhatsApp (nomor)</label>
                            <input type="text" name="company_whatsapp" value="<?= htmlspecialchars($settings['company_whatsapp'] ?? $defaults['company_whatsapp']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-300 mb-1">Alamat</label>
                            <textarea name="company_address" rows="3" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white focus:border-purple-500 focus:outline-none transition"><?= htmlspecialchars($settings['company_address'] ?? $defaults['company_address']) ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="mt-6 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-2 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300">💾 Simpan Pengaturan</button>
                </form>
            </div>
        </div>
        
        <!-- Side Settings -->
        <div class="space-y-6">
            <!-- Maintenance Mode -->
            <div class="setting-card rounded-2xl p-6">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 🔧 Maintenance Mode
                </h3>
                <p class="text-gray-400 text-sm mb-4">Aktifkan mode pemeliharaan untuk menonaktifkan akses sementara ke website.</p>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-300">Status:</span>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $maintenance_status == 'on' ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400' ?>">
                        <?= $maintenance_status == 'on' ? '🔴 Maintenance Aktif' : '🟢 Normal' ?>
                    </span>
                </div>
                <div class="flex gap-3 mt-4">
                    <?php if($maintenance_status == 'off'): ?>
                    <a href="?maintenance=on" class="flex-1 bg-red-600 text-white py-2 rounded-lg text-center hover:bg-red-500 transition" onclick="return confirm('Aktifkan maintenance mode? Website akan tidak bisa diakses oleh pengguna.')">Aktifkan Maintenance</a>
                    <?php else: ?>
                    <a href="?maintenance=off" class="flex-1 bg-green-600 text-white py-2 rounded-lg text-center hover:bg-green-500 transition">Nonaktifkan Maintenance</a>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    💡 Tips: Admin tetap bisa mengakses semua halaman saat maintenance aktif.
                </p>
            </div>
            
            <!-- Log Management Section -->
            <div class="setting-card rounded-2xl p-6 <?= !empty($log_stats) ? 'danger-card' : '' ?>">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-red-500 rounded-full"></span> 📋 Manajemen Logs
                </h3>
                
                <?php if(!empty($log_stats)): ?>
                <div class="mb-4 p-3 bg-[#0F172A] rounded-lg">
                    <p class="text-xs text-gray-400 mb-2">📊 Statistik Logs Saat Ini:</p>
                    <div class="space-y-1 max-h-32 overflow-y-auto">
                        <?php foreach($log_stats as $table => $count): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400"><?= htmlspecialchars(str_replace('_', ' ', $table)) ?>:</span>
                            <span class="text-white font-semibold"><?= number_format($count) ?> records</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-4 p-3 bg-[#0F172A] rounded-lg text-center text-gray-400 text-sm">
                    Tidak ada data logs
                </div>
                <?php endif; ?>
                
                <p class="text-gray-400 text-sm mb-4">Kelola riwayat logs sistem untuk menjaga performa database.</p>
                
                <div class="space-y-3">
                    
                    <a href="?delete_all_logs=confirm" 
                       class="w-full bg-red-600 text-white py-2 rounded-lg text-center hover:bg-red-700 transition block"
                       onclick="return confirm('🔴 PERINGATAN! 🔴\n\nAnda akan menghapus SEMUA riwayat logs tanpa kecuali!\n\nTabel yang akan dihapus:\n- backup_logs\n- employee_activities\n- login_attempts\n- system_logs\n- user_sessions\n\nTindakan ini TIDAK DAPAT DIBATALKAN.\n\nApakah Anda yakin ingin melanjutkan?')">
                        ⚠️ Hapus SEMUA Riwayat Logs
                    </a>
                </div>
                
             
            </div>
            
            
            <!-- Clear Cache -->
            <div class="setting-card rounded-2xl p-6">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 🗑️ Cache & Temp
                </h3>
                <p class="text-gray-400 text-sm mb-4">Bersihkan file temporary dan cache sistem.</p>
                <form method="POST" action="">
                    <input type="hidden" name="clear_cache" value="1">
                    <button type="submit" class="w-full bg-yellow-600 text-white py-2 rounded-lg hover:bg-yellow-500 transition" onclick="return confirm('Bersihkan semua file cache dan temporary?')">
                        🧹 Bersihkan Cache
                    </button>
                </form>
            </div>

              <!-- System Info -->
            <div class="setting-card rounded-2xl p-6">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> ℹ️ Informasi Sistem
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">PHP Version:</span>
                        <span class="text-white"><?= phpversion() ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Server:</span>
                        <span class="text-white"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Database:</span>
                        <span class="text-white">MySQL/MariaDB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Database Name:</span>
                        <span class="text-white">wo_office</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Last Config Update:</span>
                        <span class="text-white"><?= date('d/m/Y H:i:s') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto refresh message after 5 seconds
setTimeout(function() {
    const msgDiv = document.querySelector('.bg-gradient-to-r');
    if(msgDiv) {
        msgDiv.style.transition = 'opacity 0.5s';
        msgDiv.style.opacity = '0';
        setTimeout(function() {
            if(msgDiv && msgDiv.parentNode) {
                msgDiv.remove();
            }
        }, 500);
    }
}, 5000);
</script>

</body>
</html>