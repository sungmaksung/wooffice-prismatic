<?php
// Hapus session_start() karena sudah ada di config/database.php
require_once '../config/database.php';

$error = '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Cek sudah login belum
if(isAdmin()) {
    header('Location: index.php');
    exit();
}

// Handle login
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = md5($_POST['password']);
    
    // Catat percobaan login
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, 0)");
    $stmt->execute([$email, $ip_address, $user_agent]);
    $attempt_id = $pdo->lastInsertId();
    
    // Cek user dengan role admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password = ? AND role = 'admin' AND status = 'active'");
    $stmt->execute([$email, $password]);
    $user = $stmt->fetch();
    
    if($user) {
        // Login berhasil
        $pdo->prepare("UPDATE login_attempts SET success = 1 WHERE id = ?")->execute([$attempt_id]);
        
        // Catat session
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_logged_in'] = true;
        
        // Catat ke user_sessions
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, user_name, user_role, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $user['full_name'], $user['role'], $ip_address, $user_agent]);
        
        // Catat ke system_logs
        $stmt = $pdo->prepare("INSERT INTO system_logs (log_type, message, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute(['login', "Admin {$user['full_name']} login", "Email: {$email}", $ip_address]);
        
        header('Location: index.php');
        exit();
    } else {
        // Login gagal
        $error = 'Email atau password salah! Atau akun Anda bukan Admin.';
        
        // Cek apakah user ada tapi bukan admin
        $check = $pdo->prepare("SELECT role, status FROM users WHERE email = ?");
        $check->execute([$email]);
        $userCheck = $check->fetch();
        
        if($userCheck) {
            if($userCheck['role'] !== 'admin') {
                $error = 'Akun Anda bukan Administrator! Hanya admin yang bisa login di sini.';
            } elseif($userCheck['status'] !== 'active') {
                $error = 'Akun Anda tidak aktif. Hubungi administrator.';
            }
        }
        
        // Catat ke system_logs untuk percobaan gagal
        $stmt = $pdo->prepare("INSERT INTO system_logs (log_type, message, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute(['login_failed', "Percobaan login gagal", "Email: {$email}", $ip_address]);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .login-container {
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            position: relative;
            overflow: hidden;
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(139,92,246,0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .login-card {
            background: rgba(26,26,26,0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139,92,246,0.3);
        }
        .input-field {
            background: #0A0A0A;
            border-color: #2A2A2A;
            color: white;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            transform: translateY(-2px);
            border-color: #8B5CF6;
            box-shadow: 0 4px 12px rgba(139,92,246,0.2);
            outline: none;
        }
    </style>
</head>
<body class="min-h-screen login-container flex items-center justify-center p-4">
    <div class="max-w-md w-full relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg mb-4">
                <span class="text-4xl">👑</span>
            </div>
            <h1 class="text-3xl font-serif font-bold text-purple-400">Admin Panel</h1>
            <p class="text-sm text-gray-400 mt-1">Prismatic Organizer</p>
            <p class="text-xs text-gray-500 mt-1">🔒 Hanya untuk Administrator</p>
        </div>
        
        <!-- Login Card -->
        <div class="login-card rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <span class="text-5xl">🔐</span>
                <h2 class="text-xl font-bold text-white mt-2">Akses Administrator</h2>
                <p class="text-sm text-gray-400">Masukkan kredensial admin Anda</p>
            </div>
            
            <?php if($error): ?>
            <div class="mb-5 p-3 bg-red-900/50 border border-red-700 rounded-xl text-red-300 text-sm flex items-center gap-2">
                <span>⚠️</span> <?= $error ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Email Admin</label>
                    <input type="email" name="email" required 
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="admin@prismatic.com">
                </div>
                
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">Password</label>
                    <input type="password" name="password" required 
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="********">
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg hover:scale-[1.02] transition-all">
                    Login sebagai Admin →
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="../index.php" class="text-sm text-gray-400 hover:text-purple-400 transition">← Kembali ke Beranda</a>
            </div>
            
            <div class="mt-6 p-4 bg-purple-900/20 rounded-xl border border-purple-500/30">
                <p class="text-xs text-gray-400 text-center">
                    <span class="font-semibold text-purple-400">ℹ️ Info</span><br>
                    Halaman ini hanya untuk Administrator. Semua percobaan login dicatat.
                </p>
            </div>
        </div>
    </div>
</body>
</html>