<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$admin->execute([$admin_id]);
$admin = $admin->fetch();

$message = '';
$error = '';

// Handle update profile
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
    if($stmt->execute([$full_name, $phone, $email, $admin_id])) {
        $_SESSION['admin_name'] = $full_name;
        $_SESSION['admin_email'] = $email;
        $message = 'Profile berhasil diupdate!';
        logEmployeeActivity($pdo, "Admin mengupdate profile", 'update', 'admin_profile', $admin_id, "Nama: $full_name");
    } else {
        $error = 'Gagal mengupdate profile!';
    }
}

// Handle change password
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = md5($_POST['current_password']);
    $new_password = md5($_POST['new_password']);
    
    if($current_password !== $admin['password']) {
        $error = 'Password saat ini salah!';
    } elseif(strlen($_POST['new_password']) < 6) {
        $error = 'Password baru minimal 6 karakter!';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if($stmt->execute([$new_password, $admin_id])) {
            $message = 'Password berhasil diubah!';
            logEmployeeActivity($pdo, "Admin mengganti password", 'update', 'admin_profile', $admin_id, null);
        } else {
            $error = 'Gagal mengubah password!';
        }
    }
}

// Get admin activity log
$activities = $pdo->prepare("SELECT * FROM employee_activities WHERE employee_id = ? ORDER BY created_at DESC LIMIT 20");
$activities->execute([$admin_id]);
$activities = $activities->fetchAll();

// Get login history
$loginHistory = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY login_time DESC LIMIT 10");
$loginHistory->execute([$admin_id]);
$loginHistory = $loginHistory->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profile Admin - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(139, 92, 246, 0.2); }
        .profile-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #1E293B; border: 1px solid #334155; border-radius: 24px; max-width: 450px; width: 90%; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">👤 Profile Admin</h1>
        <p class="text-[#94A3B8] mt-1">Kelola informasi akun administrator</p>
    </div>
    
    <?php if($message): ?>
    <div class="mb-4 p-3 bg-green-500/20 border border-green-500/30 text-green-400 rounded-xl">✅ <?= $message ?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="mb-4 p-3 bg-red-500/20 border border-red-500/30 text-red-400 rounded-xl">❌ <?= $error ?></div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Info -->
        <div class="lg:col-span-2">
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center gap-6 mb-6 pb-6 border-b border-[#334155]">
                    <div class="w-24 h-24 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-full flex items-center justify-center text-3xl font-bold shadow-lg">
                        <?= substr($admin['full_name'], 0, 1) ?>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($admin['full_name']) ?></h2>
                        <p class="text-purple-400"><?= htmlspecialchars($admin['position'] ?? 'Super Administrator') ?></p>
                        <p class="text-gray-400 text-sm mt-1">Bergabung: <?= date('d F Y', strtotime($admin['created_at'])) ?></p>
                    </div>
                </div>
                
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-300 mb-1">Nama Lengkap</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($admin['full_name']) ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Email</label>
                            <input type="email" name="email" value="<?= $admin['email'] ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">No Telepon</label>
                            <input type="tel" name="phone" value="<?= $admin['phone'] ?>" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-1">Role</label>
                            <input type="text" value="Administrator" disabled class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-gray-400">
                        </div>
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-2 rounded-xl hover:shadow-lg transition">💾 Update Profile</button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="glass-card rounded-2xl p-6 mt-6">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 🔒 Ganti Password
                </h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="change_password" value="1">
                    <div>
                        <label class="block text-gray-300 mb-1">Password Saat Ini</label>
                        <input type="password" name="current_password" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1">Password Baru</label>
                        <input type="password" name="new_password" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white" placeholder="Minimal 6 karakter">
                    </div>
                    <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-xl hover:bg-yellow-500 transition">🔑 Ganti Password</button>
                </form>
            </div>
        </div>
        
        <!-- Activity & Login History -->
        <div class="space-y-6">
            <!-- Admin Activity -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 📋 Aktivitas Terakhir
                </h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php foreach($activities as $act): ?>
                    <div class="flex items-start gap-2 p-2 hover:bg-[#2D3A5E] rounded-lg transition">
                        <div class="w-6 h-6 bg-purple-500/20 rounded-full flex items-center justify-center text-xs">👑</div>
                        <div class="flex-1">
                            <p class="text-xs text-white"><?= htmlspecialchars($act['action']) ?></p>
                            <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Login History -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 🔐 Riwayat Login
                </h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php foreach($loginHistory as $login): ?>
                    <div class="flex items-start gap-2 p-2 hover:bg-[#2D3A5E] rounded-lg transition">
                        <div class="w-6 h-6 bg-green-500/20 rounded-full flex items-center justify-center text-xs">✓</div>
                        <div class="flex-1">
                            <p class="text-xs text-white">Login dari IP: <?= $login['ip_address'] ?></p>
                            <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($login['login_time'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Session Info -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 💻 Session Info
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-400">Logged in as:</span><span class="text-white"><?= $_SESSION['admin_name'] ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Session ID:</span><span class="text-white text-xs"><?= session_id() ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">IP Address:</span><span class="text-white"><?= $_SERVER['REMOTE_ADDR'] ?></span></div>
                </div>
                <a href="logout.php" class="mt-4 block text-center bg-red-600 text-white py-2 rounded-lg hover:bg-red-500 transition">🚪 Logout</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>