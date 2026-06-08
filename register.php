<?php include 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = md5($_POST['password']);
    $couple_name = trim($_POST['couple_name']);
    $wedding_date = $_POST['wedding_date'];
    $venue = trim($_POST['venue']);
    
    // VALIDASI: Tanggal tidak boleh sebelum hari ini
    $today = date('Y-m-d');
    if ($wedding_date < $today) {
        $error = 'Tanggal pernikahan tidak boleh kurang dari hari ini!';
    }
    // Cek email sudah ada
    elseif ($stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?")) {
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, couple_name, wedding_date, venue, status) 
                                   VALUES (?, ?, ?, ?, 'client', ?, ?, ?, 'pending')");
            if ($stmt->execute([$full_name, $email, $phone, $password, $couple_name, $wedding_date, $venue])) {
                $success = 'Pendaftaran berhasil! Silakan login.';
                // Reset form
                $full_name = $email = $phone = $couple_name = $wedding_date = $venue = '';
            } else {
                $error = 'Pendaftaran gagal! Silakan coba lagi.';
            }
        }
    }
}

// Get today's date for min attribute
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        
        /* Container dengan scroll */
        .register-container {
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        /* Agar scrollbar kelihatan di seluruh container */
        body {
            overflow-y: auto;
        }
        
        .register-container::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 80% 20%, rgba(255,215,0,0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        
        .register-container::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23FFD700' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
            opacity: 0.3;
            z-index: 0;
        }
        
        .register-card {
            background: rgba(26,26,26,0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,215,0,0.2);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
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
        
        .input-field::placeholder {
            color: #4B5563;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1A1A1A;
        }
        ::-webkit-scrollbar-thumb {
            background: #FFD700;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #DAA520;
        }
    </style>
</head>
<body class="register-container">
    <div class="max-w-md w-full mx-auto relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8">
          
            <h1 class="text-3xl font-serif font-bold text-[#FFD700]">Prismatic Organizer</h1>
            <p class="text-sm text-[#9CA3AF] mt-1">Wedding & Event Organizer</p>
            <p class="text-xs text-[#6B6B6B] mt-1">Truly Fantastic</p>
        </div>
        
        <!-- Register Card -->
        <div class="register-card rounded-3xl shadow-2xl p-8">
            <?php if($error): ?>
            <div class="mb-5 p-3 bg-red-900/50 border border-red-700 rounded-xl text-red-300 text-sm flex items-center gap-2">
                <span>⚠️</span> <?= $error ?>
            </div>
            <?php endif; ?>
            
            <?php if($success): ?>
            <div class="mb-5 p-3 bg-green-900/50 border border-green-700 rounded-xl text-green-300 text-sm flex items-center gap-2">
                <span>✅</span> <?= $success ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">Nama Lengkap</label>
                    <input type="text" name="full_name" required value="<?= htmlspecialchars($full_name ?? '') ?>"
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="Masukkan nama lengkap Anda">
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">Nama Pasangan</label>
                    <input type="text" name="couple_name" required value="<?= htmlspecialchars($couple_name ?? '') ?>"
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="Contoh: Andi &amp; Sinta">
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>"
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="contoh@email.com">
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">No Handphone</label>
                    <input type="tel" name="phone" required value="<?= htmlspecialchars($phone ?? '') ?>"
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="0812-3456-7890">
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" required 
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="Minimal 6 karakter">
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">Tanggal Pernikahan</label>
                    <input type="date" name="wedding_date" required value="<?= htmlspecialchars($wedding_date ?? '') ?>"
                           min="<?= $today ?>"
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all">
                    <p class="text-xs text-[#6B6B6B] mt-1">* Tidak boleh kurang dari hari ini</p>
                </div>
                
                <div>
                    <label class="block text-[#D1D5DB] text-sm font-medium mb-1">Lokasi Gedung</label>
                    <input type="text" name="venue" required value="<?= htmlspecialchars($venue ?? '') ?>"
                           class="input-field w-full px-4 py-3 border rounded-xl transition-all"
                           placeholder="Contoh: Hotel Mulia Senayan, Jakarta">
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-3 rounded-xl hover:shadow-lg hover:scale-[1.02] transition-all mt-2">
                    Daftar Sekarang →
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-[#9CA3AF]">
                    Sudah punya akun? 
                    <a href="login.php?role=client" class="text-[#FFD700] font-medium hover:underline">Login di sini</a>
                </p>
                <div class="mt-3">
                    <a href="index.php" class="text-sm text-[#9CA3AF] hover:text-[#FFD700] transition">← Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>