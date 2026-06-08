<?php
session_start();
require_once '../config/database.php';

// Get database name from existing PDO connection
try {
    $stmt = $pdo->query("SELECT DATABASE()");
    $db_name = $stmt->fetchColumn();
} catch(Exception $e) {
    $db_name = 'wo_office'; // fallback to your database name
}

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Create backup directory
$backup_dir = '../backups/';
if(!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Handle backup dengan error handling yang lebih baik
if(isset($_GET['action']) && $_GET['action'] == 'backup') {
    try {
        // Set time limit lebih lama untuk backup besar
        set_time_limit(300);
        
        $filename = 'prismatic_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Get all tables dengan metode yang lebih aman
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        if($result) {
            while($row = $result->fetch(PDO::FETCH_NUM)) {
                if(isset($row[0]) && !empty($row[0])) {
                    $tables[] = $row[0];
                }
            }
        }
        
        if(empty($tables)) {
            throw new Exception("Tidak ada tabel yang ditemukan di database!");
        }
        
        $sql = "-- Prismatic Organizer Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Server: " . $_SERVER['SERVER_NAME'] . "\n";
        $sql .= "-- Database: " . $db_name . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET AUTOCOMMIT = 0;\n";
        $sql .= "START TRANSACTION;\n\n";
        
        foreach($tables as $table) {
            // Escape table name untuk keamanan
            $table = trim($table);
            if(empty($table)) continue;
            
            // Get create table syntax dengan prepared statement
            $stmt = $pdo->prepare("SHOW CREATE TABLE `$table`");
            $stmt->execute();
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($create && isset($create['Create Table'])) {
                $sql .= "-- Table structure for table `$table`\n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create['Create Table'] . ";\n\n";
            } else {
                $sql .= "-- Error: Could not get structure for table `$table`\n\n";
                continue;
            }
            
            // Get data dengan chunking untuk menghindari memory overload
            $stmt = $pdo->prepare("SELECT * FROM `$table`");
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $sql .= "-- Dumping data for table `$table`\n";
                $rowCount = 0;
                
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_keys($row);
                    $values = array_map(function($val) use ($pdo) {
                        if($val === null) return 'NULL';
                        // Escape dengan lebih aman
                        return "'" . str_replace("'", "''", $val) . "'";
                    }, array_values($row));
                    
                    $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    $rowCount++;
                    
                    // Reset setiap 100 row untuk menghindari memory overload
                    if($rowCount % 100 == 0) {
                        if(strlen($sql) > 5000000) { // 5MB
                            file_put_contents($filepath, $sql, FILE_APPEND);
                            $sql = "";
                        }
                    }
                }
                $sql .= "\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql .= "COMMIT;\n";
        
        // Write to file
        if(file_put_contents($filepath, $sql)) {
            // Check if ZipArchive is available
            if(!class_exists('ZipArchive')) {
                // If no zip, just keep the SQL file
                $filesize = filesize($filepath);
                $message = "✅ Backup berhasil dibuat! File: " . $filename . ' (' . round($filesize / 1024, 2) . ' KB)';
                
                // Log backup
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS backup_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        admin_id INT,
                        backup_file VARCHAR(255),
                        file_size VARCHAR(50),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    $stmt = $pdo->prepare("INSERT INTO backup_logs (admin_id, backup_file, file_size) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $filename, round($filesize / 1024, 2) . ' KB']);
                } catch(PDOException $e) {
                    // Table might not exist or other error, just log to file
                    error_log("Backup log error: " . $e->getMessage());
                }
            } else {
                // Compress ke ZIP
                $zip = new ZipArchive();
                $zip_filename = $backup_dir . $filename . '.zip';
                
                if($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
                    $zip->addFile($filepath, $filename);
                    $zip->close();
                    unlink($filepath); // delete sql file after zip
                    $filesize = filesize($zip_filename);
                    
                    // Check if backup_logs table exists, if not create it
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS backup_logs (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            admin_id INT,
                            backup_file VARCHAR(255),
                            file_size VARCHAR(50),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        
                        $stmt = $pdo->prepare("INSERT INTO backup_logs (admin_id, backup_file, file_size) VALUES (?, ?, ?)");
                        $stmt->execute([$_SESSION['admin_id'], $filename . '.zip', round($filesize / 1024, 2) . ' KB']);
                    } catch(PDOException $e) {
                        // Table might not exist or other error, just log to file
                        error_log("Backup log error: " . $e->getMessage());
                    }
                    
                    $message = "✅ Backup berhasil dibuat! File: " . $filename . '.zip (' . round($filesize / 1024, 2) . ' KB)';
                } else {
                    throw new Exception("Gagal mengkompres backup! Tetapi file SQL telah disimpan.");
                }
            }
        } else {
            throw new Exception("Gagal membuat file backup! Pastikan folder backups writable.");
        }
    } catch(Exception $e) {
        $error = "❌ Error saat backup: " . $e->getMessage();
    }
}

// Handle download
if(isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = $backup_dir . $file;
    if(file_exists($filepath)) {
        // Set proper headers for download
        if(pathinfo($filepath, PATHINFO_EXTENSION) == 'zip') {
            header('Content-Type: application/zip');
        } else {
            header('Content-Type: application/sql');
        }
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($filepath);
        exit();
    } else {
        $error = "File tidak ditemukan!";
    }
}

// Handle delete
if(isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filepath = $backup_dir . $file;
    if(file_exists($filepath)) {
        unlink($filepath);
        $message = "🗑️ File backup berhasil dihapus!";
    } else {
        $error = "File tidak ditemukan!";
    }
}

// Get backup files dengan sorting berdasarkan tanggal
$backup_files = glob($backup_dir . '*.{zip,sql}', GLOB_BRACE);
if($backup_files === false) $backup_files = [];
usort($backup_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Get backup logs dengan error handling
$backup_logs = [];
try {
    $stmt = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20");
    if($stmt) {
        $backup_logs = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    // Table might not exist, ignore
    $backup_logs = [];
}

// Hitung total backup size
$total_size = 0;
foreach($backup_files as $file) {
    $total_size += filesize($file);
}
$total_size_mb = round($total_size / 1048576, 2); // Convert to MB
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Backup Database - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { 
            background: rgba(30, 41, 59, 0.6); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .gradient-border {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1px;
            border-radius: 1rem;
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.8));
            border: 1px solid rgba(139, 92, 246, 0.1);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .custom-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: #1E293B;
            border-radius: 10px;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .admin-main-content {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <!-- Header dengan stats -->
    <div class="mb-8">
        <div class="flex justify-between items-end">
            <div>
                <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">💾 Backup & Restore Database</h1>
                <p class="text-[#94A3B8] mt-1">Kelola backup database dengan aman dan mudah</p>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500">Total Backup Size</span>
                <p class="text-2xl font-bold text-white"><?= $total_size_mb ?> MB</p>
            </div>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if($message): ?>
    <div class="mb-4 p-4 bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-500/30 text-green-400 rounded-xl flex items-center gap-3">
        <span class="text-2xl">✅</span>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="mb-4 p-4 bg-gradient-to-r from-red-500/20 to-rose-500/20 border border-red-500/30 text-red-400 rounded-xl flex items-center gap-3">
        <span class="text-2xl">❌</span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Backup Action Panel -->
        <div class="space-y-6">
            <!-- Create Backup Card -->
            <div class="gradient-border">
                <div class="glass-card rounded-2xl p-6 text-center bg-[#1E293B]">
                    <div class="text-7xl mb-4">💾</div>
                    <h3 class="text-xl font-bold text-white mb-2">Buat Backup Baru</h3>
                    <p class="text-gray-400 text-sm mb-4">
                        Membuat backup lengkap database dalam format ZIP<br>
                        <span class="text-xs text-purple-400">✨ Includes all tables & data</span>
                    </p>
                    <a href="?action=backup" 
                       class="inline-flex items-center gap-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-8 py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 font-semibold" 
                       onclick="return confirm('⚠️ Buat backup database sekarang?\n\nProses ini mungkin memakan waktu beberapa saat tergantung ukuran database.')">
                        <span>🔒</span>
                        <span>Buat Backup Sekarang</span>
                    </a>
                </div>
            </div>
            
            <!-- Storage Info -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-2xl">📊</span>
                    <h3 class="font-semibold text-white">Storage Usage</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Total Backup Files:</span>
                        <span class="text-white font-semibold"><?= count($backup_files) ?> files</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Total Size:</span>
                        <span class="text-white font-semibold"><?= $total_size_mb ?> MB</span>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-700">
                        <div class="bg-[#0F172A] rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-purple-500 to-pink-500 h-2 rounded-full" style="width: <?= min(($total_size_mb / 100) * 100, 100) ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2"><?= round(($total_size_mb / 100) * 100, 1) ?>% of 100MB limit</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-2xl">💡</span>
                    <h3 class="font-semibold text-white">Quick Tips</h3>
                </div>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li class="flex gap-2">• Backup sebelum melakukan update besar</li>
                    <li class="flex gap-2">• Simpan backup di tempat yang aman</li>
                    <li class="flex gap-2">• Hapus backup lama untuk menghemat space</li>
                    <li class="flex gap-2">• Gunakan jadwal backup otomatis (coming soon)</li>
                </ul>
            </div>
        </div>
        
        <!-- Backup Files List -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Files Table -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-[#334155] bg-[#1E293B]/50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📁</span>
                            <div>
                                <h3 class="font-semibold text-white">Daftar File Backup</h3>
                                <p class="text-xs text-gray-400 mt-1">Klik download untuk menyimpan backup</p>
                            </div>
                        </div>
                        <?php if(!empty($backup_files)): ?>
                        <span class="text-xs bg-purple-500/20 text-purple-400 px-3 py-1 rounded-full">
                            <?= count($backup_files) ?> file(s)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="divide-y divide-[#334155] max-h-[400px] overflow-y-auto custom-scroll">
                    <?php if(empty($backup_files)): ?>
                    <div class="p-12 text-center">
                        <div class="text-6xl mb-4 opacity-50">📂</div>
                        <p class="text-gray-400 font-medium">Belum ada file backup</p>
                        <p class="text-sm text-gray-500 mt-2">Klik tombol "Buat Backup Sekarang" untuk membuat backup pertama</p>
                    </div>
                    <?php else: ?>
                        <?php foreach($backup_files as $file): 
                            $filename = basename($file);
                            $filesize = round(filesize($file) / 1024, 2);
                            $filedate = date('d F Y H:i:s', filemtime($file));
                            $fileclass = '';
                            if($filesize > 5000) $fileclass = 'text-orange-400';
                            elseif($filesize > 10000) $fileclass = 'text-red-400';
                            else $fileclass = 'text-green-400';
                        ?>
                        <div class="p-4 flex justify-between items-center hover:bg-[#2D3A5E] transition-all duration-200 group">
                            <div class="flex items-center gap-4">
                                <div class="text-3xl">
                                    <?php if(strpos($filename, 'auto') !== false): ?>
                                    🤖
                                    <?php else: ?>
                                    💾
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="font-medium text-white group-hover:text-purple-400 transition"><?= htmlspecialchars($filename) ?></p>
                                    <div class="flex gap-3 text-xs mt-1">
                                        <span class="text-gray-400">📅 <?= $filedate ?></span>
                                        <span class="<?= $fileclass ?>">📦 <?= $filesize ?> KB</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 opacity-100 lg:opacity-0 lg:group-hover:opacity-100 transition-all duration-200">
                                <a href="?download=<?= urlencode($filename) ?>" 
                                   class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg text-sm hover:shadow-lg transition-all flex items-center gap-1">
                                    📥 Download
                                </a>
                                <a href="?delete=<?= urlencode($filename) ?>" 
                                   class="bg-gradient-to-r from-red-600 to-red-700 text-white px-4 py-2 rounded-lg text-sm hover:shadow-lg transition-all flex items-center gap-1"
                                   onclick="return confirm('⚠️ Hapus file backup <?= htmlspecialchars($filename) ?>?\n\nFile ini akan dihapus secara permanen!')">
                                    🗑️ Hapus
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Backup History -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-[#334155] bg-[#1E293B]/50">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">📋</span>
                        <div>
                            <h3 class="font-semibold text-white">Riwayat Backup</h3>
                            <p class="text-xs text-gray-400 mt-1">20 backup terakhir yang dibuat</p>
                        </div>
                    </div>
                </div>
                <div class="divide-y divide-[#334155] max-h-[300px] overflow-y-auto custom-scroll">
                    <?php if(empty($backup_logs)): ?>
                    <div class="p-12 text-center">
                        <span class="text-4xl mb-2 block opacity-50">📭</span>
                        <p class="text-gray-400">Belum ada riwayat backup</p>
                    </div>
                    <?php else: ?>
                        <?php foreach($backup_logs as $log): ?>
                        <div class="p-3 flex justify-between items-center hover:bg-[#2D3A5E] transition">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">✅</span>
                                <div>
                                    <p class="text-sm text-white"><?= htmlspecialchars($log['backup_file']) ?></p>
                                    <p class="text-xs text-gray-500"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></p>
                                </div>
                            </div>
                            <span class="text-xs bg-[#0F172A] px-3 py-1 rounded-full text-purple-400"><?= htmlspecialchars($log['file_size']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>