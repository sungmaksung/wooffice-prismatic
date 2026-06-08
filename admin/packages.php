<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle Add Package
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    $name = $_POST['name'];
    $slug = strtolower(str_replace(' ', '_', $name));
    $price = $_POST['price'];
    $description = $_POST['description'];
    $features = $_POST['features'];
    $is_custom = isset($_POST['is_custom']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO packages (name, slug, price, description, features, is_custom) VALUES (?, ?, ?, ?, ?, ?)");
    if($stmt->execute([$name, $slug, $price, $description, $features, $is_custom])) {
        $message = 'Paket berhasil ditambahkan!';
    } else {
        $error = 'Gagal menambahkan paket!';
    }
}

// Handle Edit Package
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_package'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $description = $_POST['description'];
    $features = $_POST['features'];
    
    $stmt = $pdo->prepare("UPDATE packages SET name = ?, price = ?, description = ?, features = ? WHERE id = ?");
    if($stmt->execute([$name, $price, $description, $features, $id])) {
        $message = 'Paket berhasil diupdate!';
    } else {
        $error = 'Gagal mengupdate paket!';
    }
}

// Handle Delete Package
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
    if($stmt->execute([$id])) {
        $message = 'Paket berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus paket!';
    }
    header("Location: packages.php?msg=" . urlencode($message));
    exit();
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY id")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Paket - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(139, 92, 246, 0.2); }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #1E293B; border: 1px solid #334155; border-radius: 24px; max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto; }
        .package-card { background: #1E293B; border: 1px solid #334155; transition: all 0.3s; }
        .package-card:hover { transform: translateY(-4px); border-color: #8B5CF6; }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">📦 Manajemen Paket</h1>
            <p class="text-[#94A3B8] mt-1">Kelola paket wedding yang ditawarkan</p>
        </div>
        <button onclick="openAddModal()" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2 rounded-xl hover:shadow-lg transition">+ Tambah Paket</button>
    </div>
    
    <?php if($msg): ?>
    <div class="mb-4 p-3 bg-green-500/20 border border-green-500/30 text-green-400 rounded-xl">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($packages as $pkg): ?>
        <div class="package-card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-4 text-white">
                <h3 class="font-semibold text-lg"><?= htmlspecialchars($pkg['name']) ?></h3>
                <p class="text-purple-200 text-sm">Slug: <?= $pkg['slug'] ?></p>
            </div>
            <div class="p-4">
                <p class="text-2xl font-bold text-yellow-400">Rp <?= number_format($pkg['price'], 0, ',', '.') ?></p>
                <p class="text-gray-400 text-sm mt-2 line-clamp-2"><?= htmlspecialchars(substr($pkg['description'], 0, 100)) ?>...</p>
                <div class="mt-3 flex gap-2">
                    <button onclick="openEditModal(<?= $pkg['id'] ?>, '<?= addslashes($pkg['name']) ?>', <?= $pkg['price'] ?>, '<?= addslashes($pkg['description']) ?>', '<?= addslashes($pkg['features']) ?>')" class="flex-1 bg-blue-600 text-white py-1 rounded-lg text-sm">Edit</button>
                    <a href="?delete=<?= $pkg['id'] ?>" class="flex-1 bg-red-600 text-white py-1 rounded-lg text-sm text-center" onclick="return confirm('Hapus paket ini?')">Hapus</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Add Package -->
<div id="addModal" class="modal">
    <div class="modal-content p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-white">➕ Tambah Paket</h2>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_package" value="1">
            <div class="space-y-4">
                <div><label class="block text-gray-300 mb-1">Nama Paket</label><input type="text" name="name" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Harga</label><input type="number" name="price" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Deskripsi</label><textarea name="description" rows="3" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></textarea></div>
                <div><label class="block text-gray-300 mb-1">Fitur (pisahkan dengan koma)</label><textarea name="features" rows="3" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white" placeholder="Contoh: Dekorasi, Dokumentasi, MC"></textarea></div>
                <div><label class="flex items-center gap-2"><input type="checkbox" name="is_custom" value="1" class="w-4 h-4"> <span class="text-gray-300">Paket Custom</span></label></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg">Simpan</button><button type="button" onclick="closeAddModal()" class="flex-1 bg-gray-600 text-white py-2 rounded-lg">Batal</button></div>
        </form>
    </div>
</div>

<!-- Modal Edit Package -->
<div id="editModal" class="modal">
    <div class="modal-content p-6">
        <div class="flex justify-between items-center mb-4"><h2 class="text-xl font-bold text-white">✏️ Edit Paket</h2><button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="edit_package" value="1"><input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div><label class="block text-gray-300 mb-1">Nama Paket</label><input type="text" name="name" id="edit_name" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Harga</label><input type="number" name="price" id="edit_price" required class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></div>
                <div><label class="block text-gray-300 mb-1">Deskripsi</label><textarea name="description" id="edit_desc" rows="3" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></textarea></div>
                <div><label class="block text-gray-300 mb-1">Fitur</label><textarea name="features" id="edit_features" rows="3" class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white"></textarea></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg">Simpan</button><button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-600 text-white py-2 rounded-lg">Batal</button></div>
        </form>
    </div>
</div>

<script>
    function openAddModal() { document.getElementById('addModal').classList.add('active'); }
    function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }
    function openEditModal(id, name, price, desc, features) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_desc').value = desc;
        document.getElementById('edit_features').value = features;
        document.getElementById('editModal').classList.add('active');
    }
    function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }
</script>
</body>
</html>