<?php
include 'config/database.php';

$error = '';
$role = $_GET['role'] ?? 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = md5($_POST['password']);
    $role_input = $_POST['role'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password = ? AND role = ?");
    $stmt->execute([$email, $password, $role_input]);
    $user = $stmt->fetch();
    
    if ($user) {
        // CEK STATUS UNTUK CLIENT DAN EMPLOYEE
        if ($role_input === 'client' && $user['status'] !== 'active') {
            $error = 'Akun Anda belum diaktifkan oleh admin. Silakan tunggu konfirmasi!';
        } elseif ($role_input === 'employee' && $user['status'] !== 'active') {
            $error = 'Akun karyawan Anda telah dinonaktifkan oleh admin. Hubungi administrator!';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_photo'] = $user['profile_picture'];
            $_SESSION['user_position'] = $user['position'];
            $_SESSION['user_status'] = $user['status'];
            $_SESSION['is_logged_in'] = true;
            
            if ($user['role'] === 'employee') {
                header('Location: employee/dashboard.php');
            } else {
                header('Location: client/dashboard.php');
            }
            exit();
        }
    } else {
        $error = 'Email atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Prismatic Organizer</title>
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
            background: radial-gradient(circle at 20% 30%, rgba(255,215,0,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .login-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23FFD700' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
            opacity: 0.3;
        }
        .login-card {
            background: rgba(26,26,26,0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,215,0,0.2);
            transition: all 0.3s ease;
        }
        .input-field {
            background: #0A0A0A;
            border-color: #2A2A2A;
            color: white;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            transform: translateY(-2px);
            border-color: #FFD700;
            box-shadow: 0 4px 12px rgba(255,215,0,0.15);
            outline: none;
        }
        .role-btn {
            transition: all 0.3s ease;
            background: #1A1A1A;
            color: #9CA3AF;
            border: 1px solid #2A2A2A;
        }
        .role-btn.active {
            background: linear-gradient(135deg, #FFD700, #DAA520);
            color: #0A0A0A;
            box-shadow: 0 4px 12px rgba(255,215,0,0.3);
            border-color: transparent;
        }
        .role-btn:hover:not(.active) {
            background: #252525;
            color: #FFD700;
            border-color: #FFD700;
        }
    </style>
</head>
<body class="min-h-screen login-container flex items-center justify-center p-4">
    <div class="max-w-md w-full relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-serif font-bold text-[#FFD700]">Prismatic Organizer</h1>
            <p class="text-sm text-[#9CA3AF] mt-1">Wedding & Event Organizer</p>
            <p class="text-xs text-[#6B6B6B] mt-1">Truly Fantastic</p>
        </div>
        
        <!-- Login Card -->
        <div class="login-card rounded-3xl shadow-2xl p-8">
            <!-- Role Selection -->
            <div class="flex gap-3 mb-8">
                <a href="?role=client" 
                   class="role-btn flex-1 text-center py-3 rounded-xl font-semibold transition-all <?= $role == 'client' ? 'active' : '' ?>">
                    💑 Client
                </a>
                <a href="?role=employee" 
                   class="role-btn flex-1 text-center py-3 rounded-xl font-semibold transition-all <?= $role == 'employee' ? 'active' : '' ?>">
                    👩‍💼 Employee
                </a>
            </div>
            
            <!-- Error Message -->
            <?php if($error): ?>
            <div class="mb-5 p-3 bg-red-900/50 border border-red-700 rounded-xl text-red-300 text-sm flex items-center gap-2">
                <span>⚠️</span> <?= $error ?>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" class="space-y-5">
                <input type="hidden" name="role" value="<?= $role ?>">
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-2">Email Address</label>
                    <input type="email" name="email" required 
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="contoh@email.com">
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-2">Password</label>
                    <input type="password" name="password" required 
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="********">
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-3 rounded-xl hover:shadow-lg hover:scale-[1.02] transition-all">
                    Login →
                </button>
            </form>
            
            <!-- Additional Links -->
            <div class="mt-6 text-center">
                <?php if($role == 'client'): ?>
                    <p class="text-sm text-[#9CA3AF]">
                        Belum punya akun? 
                        <a href="register.php" class="text-[#FFD700] font-medium hover:underline">Daftar Sekarang</a>
                    </p>
                    <p class="text-xs text-[#6B6B6B] mt-3">
                        💡 Akun akan diaktifkan oleh admin setelah pendaftaran
                    </p>
                <?php endif; ?>
                <div class="mt-4">
                    <a href="index.php" class="text-sm text-[#9CA3AF] hover:text-[#FFD700] transition">← Kembali ke Beranda</a>
                </div>
            </div>
            
            <!-- Demo Credentials -->
            <div class="mt-6 p-4 bg-[#1A1A1A] rounded-xl border border-[#2A2A2A]">
                <p class="text-xs text-[#9CA3AF] text-center">
                    <span class="font-semibold text-[#FFD700]">Demo Account</span><br>
                    <?php if($role == 'employee'): ?>
                        <strong>Employee:</strong> admin@wooffice.com / admin123
                    <?php else: ?>
                        <strong>Client:</strong> client@wooffice.com / client123
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html>