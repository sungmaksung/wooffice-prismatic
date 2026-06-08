<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $couple_name = $_POST['couple_name'];
    $wedding_date = $_POST['wedding_date'];
    $venue = $_POST['venue'];
    
    // Profile picture
    $profile_picture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $upload_dir = '../uploads/profiles/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = 'client_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $filename);
        $profile_picture = $filename;
    }
    
    // Password change
    if (!empty($_POST['new_password'])) {
        if (md5($_POST['current_password']) === $user['password']) {
            $new_password = md5($_POST['new_password']);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_password, $user_id]);
            $success = 'Password berhasil diubah!';
        } else {
            $error = 'Password saat ini salah!';
        }
    }
    
    if (!$error) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, couple_name = ?, wedding_date = ?, venue = ?, profile_picture = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $email, $couple_name, $wedding_date, $venue, $profile_picture, $user_id]);
        
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_email'] = $email;
        
        $success = 'Profile berhasil diupdate!';
        header("Refresh:0");
    }
}

// Hitung hari menuju pernikahan
$days_to_wedding = '-';
$wedding_status = '';
if ($user['wedding_date']) {
    $diff = ceil((strtotime($user['wedding_date']) - time()) / 86400);
    if ($diff > 0) {
        $days_to_wedding = $diff;
        $wedding_status = "H-$diff menuju hari bahagia! 🎉";
    } elseif ($diff == 0) {
        $days_to_wedding = 0;
        $wedding_status = "🎊 SELAMAT HARI INI HARI PERNIKAHAN ANDA! 🎊";
    } else {
        $days_to_wedding = abs($diff);
        $wedding_status = "Sudah {$days_to_wedding} hari berlalu, semoga bahagia selalu! 💕";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Profile - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="font-serif text-3xl font-semibold text-[#FFD700]">⚙️ Pengaturan Profile</h1>
            <p class="text-[#9CA3AF] mt-1">Kelola data diri dan informasi pernikahan Anda</p>
        </div>
        
        <!-- Wedding Status Banner -->
        <?php if($wedding_status): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-[#FFD700]/10 to-[#DAA520]/10 rounded-xl border border-[#FFD700]/20">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📅</span>
                <p class="text-[#FFD700] font-medium"><?= $wedding_status ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Profile Card -->
        <div class="bg-[#1A1A1A] rounded-2xl border border-[#2A2A2A] overflow-hidden">
            <div class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] p-6 text-[#0A0A0A]">
                <h2 class="font-serif text-xl font-semibold">Informasi Pribadi</h2>
                <p class="text-[#0A0A0A]/70 text-sm">Update data diri untuk pengalaman lebih baik</p>
            </div>
            
            <div class="p-6">
                <?php if($success): ?>
                <div class="bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded-xl mb-4">✅ <?= $success ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="bg-red-900 border border-red-700 text-red-300 px-4 py-3 rounded-xl mb-4">❌ <?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Photo Section -->
                    <div class="flex items-center gap-6 pb-4 border-b border-[#2A2A2A]">
                        <div class="relative">
                            <img src="../uploads/profiles/<?= $user['profile_picture'] ?>" 
                                 class="w-24 h-24 rounded-full object-cover border-4 border-[#FFD700] shadow-md"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=FFD700&color=000&size=96'">
                            <div class="absolute bottom-0 right-0 bg-[#FFD700] rounded-full p-1">
                                <label class="cursor-pointer text-[#0A0A0A] text-xs">📷
                                    <input type="file" name="profile_picture" accept="image/*" class="hidden" onchange="this.form.submit()">
                                </label>
                            </div>
                        </div>
                        <div>
                            <p class="font-medium text-white">Foto Profile</p>
                            <p class="text-xs text-[#9CA3AF]">Klik ikon kamera untuk mengganti foto</p>
                            <p class="text-xs text-[#FFD700] mt-1">Format: JPG, PNG (Max 2MB)</p>
                        </div>
                    </div>
                    
                    <!-- Form Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-white mb-1">Nama Lengkap</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" 
                                   class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
                        </div>
                        <div>
                            <label class="block text-white mb-1">Nama Pasangan</label>
                            <input type="text" name="couple_name" value="<?= htmlspecialchars($user['couple_name']) ?>" 
                                   class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]"
                                   placeholder="Contoh: Andi & Sinta">
                        </div>
                        <div>
                            <label class="block text-white mb-1">Email</label>
                            <input type="email" name="email" value="<?= $user['email'] ?>" 
                                   class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
                        </div>
                        <div>
                            <label class="block text-white mb-1">No HP</label>
                            <input type="tel" name="phone" value="<?= $user['phone'] ?>" 
                                   class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
                        </div>
                        <div>
                            <label class="block text-white mb-1">Tanggal Pernikahan</label>
                            <input type="date" name="wedding_date" value="<?= $user['wedding_date'] ?>" 
                                   class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
                        </div>
                        <div>
                            <label class="block text-white mb-1">Lokasi Gedung</label>
                            <input type="text" name="venue" value="<?= htmlspecialchars($user['venue']) ?>" 
                                   class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]"
                                   placeholder="Contoh: Hotel Mulia Senayan">
                        </div>
                    </div>
                    
                    <!-- Change Password Section -->
                    <div class="border-t border-[#2A2A2A] pt-6 mt-4">
                        <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                            <span class="text-xl">🔒</span> Ganti Password
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-white mb-1">Password Saat Ini</label>
                                <input type="password" name="current_password" 
                                       class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
                            </div>
                            <div>
                                <label class="block text-white mb-1">Password Baru</label>
                                <input type="password" name="new_password" 
                                       class="w-full px-4 py-2 border border-[#2A2A2A] rounded-xl bg-[#0A0A0A] text-white focus:outline-none focus:border-[#FFD700]">
                            </div>
                        </div>
                        <p class="text-xs text-[#9CA3AF] mt-2">* Kosongkan jika tidak ingin mengganti password</p>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-8 py-2.5 rounded-full hover:shadow-lg transition">
                            💾 Simpan Perubahan
                        </button>
                        <a href="dashboard.php" class="border border-[#2A2A2A] text-[#9CA3AF] px-6 py-2.5 rounded-full hover:bg-[#2A2A2A] transition">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Info -->
        <div class="mt-6 p-4 bg-[#1A1A1A] rounded-xl text-center border border-[#2A2A2A]">
            <p class="text-sm text-[#9CA3AF]">
                <span class="font-medium text-white">Akun dibuat pada:</span> 
                <?= date('d F Y', strtotime($user['created_at'])) ?>
            </p>
            <p class="text-xs text-[#FFD700] mt-1">💕 Terima kasih telah mempercayakan hari bahagia Anda kepada Prismatic Organizer</p>
        </div>
    </div>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
</script>
</body>
</html>