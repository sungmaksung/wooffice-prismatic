<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle new post
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $content = $_POST['content'];
    $image = null;
    
    if(isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_dir = '../uploads/forum/';
        if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $filename = time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename);
        $image = 'uploads/forum/' . $filename;
    }
    
    if(!empty($content) || $image) {
        $stmt = $pdo->prepare("INSERT INTO forum_posts (user_id, content, image) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $content, $image]);
        logEmployeeActivity($pdo, "Memposting curhat: " . substr($content ?: 'Upload foto', 0, 50), 'post', 'forum', $pdo->lastInsertId(), $content);
    }
    header("Location: forum.php");
    exit();
}

// Handle edit post (refresh tetap diperlukan untuk edit karena mengubah konten)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_post'])) {
    $post_id = $_POST['post_id'];
    $content = $_POST['content'];
    
    $check = $pdo->prepare("SELECT user_id FROM forum_posts WHERE id = ?");
    $check->execute([$post_id]);
    $post_owner = $check->fetchColumn();
    
    if($post_owner == $user_id) {
        $stmt = $pdo->prepare("UPDATE forum_posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$content, $post_id]);
        logEmployeeActivity($pdo, "Mengedit postingan forum ID: $post_id", 'update', 'forum', $post_id, "Konten baru: " . substr($content, 0, 50));
    }
    header("Location: forum.php");
    exit();
}

// Handle delete post
if(isset($_GET['delete_post'])) {
    $post_id = $_GET['delete_post'];
    $check = $pdo->prepare("SELECT user_id, content FROM forum_posts WHERE id = ?");
    $check->execute([$post_id]);
    $post = $check->fetch();
    
    if($post && $post['user_id'] == $user_id) {
        $pdo->prepare("DELETE FROM forum_posts WHERE id = ?")->execute([$post_id]);
        logEmployeeActivity($pdo, "Menghapus postingan forum ID: $post_id", 'delete', 'forum', $post_id, "Konten: " . substr($post['content'], 0, 50));
    }
    header("Location: forum.php");
    exit();
}

// Handle edit comment
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment'])) {
    $comment_id = $_POST['comment_id'];
    $comment_text = $_POST['comment_text'];
    
    $check = $pdo->prepare("SELECT user_id, post_id FROM forum_comments WHERE id = ?");
    $check->execute([$comment_id]);
    $comment = $check->fetch();
    
    if($comment && $comment['user_id'] == $user_id) {
        $stmt = $pdo->prepare("UPDATE forum_comments SET comment = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$comment_text, $comment_id]);
        logEmployeeActivity($pdo, "Mengedit komentar forum ID: $comment_id", 'update', 'forum_comment', $comment_id, $comment_text);
    }
    header("Location: forum.php");
    exit();
}

// Handle delete comment
if(isset($_GET['delete_comment'])) {
    $comment_id = $_GET['delete_comment'];
    $check = $pdo->prepare("SELECT user_id FROM forum_comments WHERE id = ?");
    $check->execute([$comment_id]);
    $comment_owner = $check->fetchColumn();
    
    if($comment_owner == $user_id) {
        $pdo->prepare("DELETE FROM forum_comments WHERE id = ?")->execute([$comment_id]);
        logEmployeeActivity($pdo, "Menghapus komentar forum ID: $comment_id", 'delete', 'forum_comment', $comment_id, null);
    }
    header("Location: forum.php");
    exit();
}

// Handle AJAX Like/Unlike
if(isset($_GET['ajax_like'])) {
    $post_id = $_GET['ajax_like'];
    
    $check = $pdo->prepare("SELECT id FROM forum_likes WHERE post_id = ? AND user_id = ?");
    $check->execute([$post_id, $user_id]);
    $existing = $check->fetch();
    
    if($existing) {
        $pdo->prepare("DELETE FROM forum_likes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $user_id]);
        $pdo->prepare("UPDATE forum_posts SET likes = likes - 1 WHERE id = ?")->execute([$post_id]);
        logEmployeeActivity($pdo, "Batal menyukai postingan forum ID: $post_id", 'like', 'forum', $post_id, null);
        echo json_encode(['status' => 'unliked', 'likes' => $pdo->prepare("SELECT likes FROM forum_posts WHERE id = ?")->execute([$post_id])]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO forum_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$post_id, $user_id]);
        $pdo->prepare("UPDATE forum_posts SET likes = likes + 1 WHERE id = ?")->execute([$post_id]);
        logEmployeeActivity($pdo, "Menyukai postingan forum ID: $post_id", 'like', 'forum', $post_id, null);
        echo json_encode(['status' => 'liked', 'likes' => $pdo->prepare("SELECT likes FROM forum_posts WHERE id = ?")->execute([$post_id])]);
    }
    exit();
}

// Handle AJAX Comment
if(isset($_POST['ajax_comment'])) {
    $post_id = $_POST['post_id'];
    $comment = $_POST['comment'];
    
    $stmt = $pdo->prepare("INSERT INTO forum_comments (post_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $comment]);
    $comment_id = $pdo->lastInsertId();
    
    logEmployeeActivity($pdo, "Mengomentari postingan forum: " . substr($comment, 0, 50), 'comment', 'forum', $post_id, $comment);
    
    // Get comment data
    $commentData = $pdo->prepare("
        SELECT c.*, u.full_name, u.profile_picture, u.position,
               DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as time_formatted
        FROM forum_comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $commentData->execute([$comment_id]);
    $newComment = $commentData->fetch();
    
    echo json_encode([
        'status' => 'success',
        'comment_id' => $comment_id,
        'full_name' => htmlspecialchars($newComment['full_name']),
        'position' => htmlspecialchars($newComment['position'] ?? ''),
        'comment' => nl2br(htmlspecialchars($newComment['comment'])),
        'time' => $newComment['time_formatted'],
        'profile_picture' => $newComment['profile_picture'],
        'user_id' => $newComment['user_id'],
        'current_user_id' => $user_id
    ]);
    exit();
}

$stickers = ['😊', '😂', '🥰', '😍', '🎉', '💕', '✨', '👍', '🙏', '🔥', '🥺', '😭', '🤣', '😎', '🤔', '😴', '🥳', '💪', '👏', '🙌'];

$posts = $pdo->query("
    SELECT p.*, u.full_name, u.profile_picture, u.position,
           (SELECT COUNT(*) FROM forum_comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM forum_likes WHERE post_id = p.id AND user_id = $user_id) as user_liked
    FROM forum_posts p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Forum Karyawan - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        .post-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; transition: all 0.3s ease; }
        .post-card:hover { border-color: #3B82F6; transform: translateY(-2px); }
        .like-btn.liked { color: #EF4444; }
        .like-btn:not(.liked) { color: #94A3B8; }
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background: #1E293B; border: 1px solid #334155; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); z-index: 10; min-width: 120px; }
        .post-actions { position: relative; }
        .dropdown-menu a, .dropdown-menu button { display: block; padding: 8px 16px; color: #94A3B8; text-decoration: none; font-size: 13px; width: 100%; text-align: left; background: none; border: none; cursor: pointer; }
        .dropdown-menu a:hover, .dropdown-menu button:hover { background: #2D3A5E; color: white; }
        .sticker-picker { display: none; position: absolute; bottom: 100%; left: 0; background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 8px; width: 250px; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; z-index: 20; }
        .sticker-picker span { font-size: 24px; cursor: pointer; padding: 4px; transition: transform 0.2s; }
        .sticker-picker span:hover { transform: scale(1.2); background: #2D3A5E; border-radius: 8px; }
        .image-preview { position: relative; display: inline-block; margin-top: 12px; }
        .image-preview img { max-height: 150px; border-radius: 12px; border: 1px solid #334155; }
        .remove-image { position: absolute; top: -8px; right: -8px; background: #EF4444; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 16px 24px; } }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="max-w-3xl mx-auto">
        <div class="mb-8">
            <h1 class="font-serif text-3xl font-semibold text-[#60A5FA]">💬 Forum Karyawan</h1>
            <p class="text-[#94A3B8] mt-1">Share cerita, curhat, atau diskusi dengan tim</p>
        </div>
        
        <!-- Post Form -->
        <div class="post-card rounded-2xl p-5 mb-8">
            <div class="flex items-center gap-3 mb-4">
                <img src="../uploads/profiles/<?= $_SESSION['user_photo'] ?? 'default.png' ?>" class="w-10 h-10 rounded-full object-cover border-2 border-blue-500" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=2563EB&color=fff'">
                <div>
                    <p class="font-semibold text-white"><?= htmlspecialchars($user_name) ?></p>
                    <p class="text-xs text-gray-400"><?= $_SESSION['user_position'] ?? 'Employee' ?></p>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data" id="postForm">
                <textarea name="content" rows="3" class="w-full px-4 py-3 bg-[#0F172A] border border-[#334155] rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 transition" placeholder="Ada yang mau diceritain hari ini? Curhat yuk! 😊"></textarea>
                <div class="flex items-center justify-between mt-3">
                    <div class="flex gap-3">
                        <label class="cursor-pointer text-gray-400 hover:text-blue-400 transition">
                            <input type="file" name="image" accept="image/*" class="hidden" id="imageInput" onchange="previewImage(this)">
                            📷 Tambahkan Foto
                        </label>
                    </div>
                    <button type="submit" name="submit_post" class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-5 py-2 rounded-xl hover:shadow-lg transition font-medium">Posting →</button>
                </div>
                <div id="imagePreviewContainer" class="image-preview hidden mt-3">
                    <img id="imagePreview" src="" alt="Preview">
                    <span class="remove-image" onclick="removeImage()">✕</span>
                </div>
            </form>
        </div>
        
        <!-- Posts List -->
        <div class="space-y-6" id="postsContainer">
            <?php foreach($posts as $post): ?>
            <div class="post-card rounded-2xl overflow-hidden" id="post-<?= $post['id'] ?>" data-post-id="<?= $post['id'] ?>">
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <img src="../uploads/profiles/<?= $post['profile_picture'] ?>" class="w-12 h-12 rounded-full object-cover border-2 border-blue-500" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($post['full_name']) ?>&background=2563EB&color=fff'">
                            <div>
                                <p class="font-semibold text-white"><?= htmlspecialchars($post['full_name']) ?></p>
                                <div class="flex items-center gap-2 text-xs text-gray-400">
                                    <span><?= htmlspecialchars($post['position'] ?? 'Staff') ?></span>
                                    <span>•</span>
                                    <span><?= date('d F Y H:i', strtotime($post['created_at'])) ?></span>
                                    <?php if($post['updated_at'] && $post['updated_at'] != $post['created_at']): ?>
                                    <span class="text-gray-500">(diedit)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if($post['user_id'] == $user_id): ?>
                        <div class="post-actions">
                            <button class="text-gray-400 hover:text-white text-xl" onclick="toggleDropdown(<?= $post['id'] ?>)">⋯</button>
                            <div id="dropdown-<?= $post['id'] ?>" class="dropdown-menu">
                                <button onclick="editPost(<?= $post['id'] ?>, `<?= addslashes($post['content']) ?>`)">✏️ Edit</button>
                                <a href="?delete_post=<?= $post['id'] ?>" onclick="return confirm('Hapus postingan ini?')">🗑️ Hapus</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="post-content" id="post-content-<?= $post['id'] ?>">
                        <p class="text-gray-300 mb-3 leading-relaxed"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                    </div>
                    <div class="edit-form hidden" id="edit-form-<?= $post['id'] ?>">
                        <form method="POST">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <textarea name="content" rows="3" class="w-full px-4 py-3 bg-[#0F172A] border border-[#334155] rounded-xl text-white"><?= htmlspecialchars($post['content']) ?></textarea>
                            <div class="flex gap-2 mt-2">
                                <button type="submit" name="edit_post" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm">Simpan</button>
                                <button type="button" onclick="cancelEdit(<?= $post['id'] ?>)" class="bg-gray-600 text-white px-3 py-1 rounded-lg text-sm">Batal</button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if($post['image']): ?>
                    <img src="../<?= $post['image'] ?>" class="rounded-xl mb-4 max-h-80 object-cover cursor-pointer" onclick="window.open(this.src)">
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-6 pt-3 border-t border-[#334155]">
                        <button class="like-btn flex items-center gap-2 transition <?= $post['user_liked'] ? 'liked text-red-500' : 'text-gray-400 hover:text-red-500' ?>" data-post-id="<?= $post['id'] ?>">
                            <span class="text-xl">❤️</span>
                            <span class="likes-count"><?= $post['likes'] ?></span>
                        </button>
                        <button class="comment-toggle flex items-center gap-2 text-gray-400 hover:text-blue-400 transition" data-post-id="<?= $post['id'] ?>">
                            💬 <span class="comment-count"><?= $post['comment_count'] ?></span>
                        </button>
                    </div>
                    
                    <!-- Comments Section -->
                    <div id="comments-<?= $post['id'] ?>" class="hidden mt-4 pt-4 border-t border-[#334155]">
                        <div class="comments-list space-y-3 max-h-80 overflow-y-auto pr-2" id="comments-list-<?= $post['id'] ?>">
                            <?php
                            $comments = $pdo->prepare("SELECT c.*, u.full_name, u.profile_picture, u.position, DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as time_formatted FROM forum_comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
                            $comments->execute([$post['id']]);
                            while($comment = $comments->fetch()):
                            ?>
                            <div class="flex gap-3 group" id="comment-<?= $comment['id'] ?>" data-comment-id="<?= $comment['id'] ?>">
                                <img src="../uploads/profiles/<?= $comment['profile_picture'] ?>" class="w-8 h-8 rounded-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($comment['full_name']) ?>&background=2563EB&color=fff'">
                                <div class="flex-1 bg-[#0F172A] rounded-xl p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-sm text-white"><?= htmlspecialchars($comment['full_name']) ?></span>
                                            <span class="text-xs text-gray-500"><?= htmlspecialchars($comment['position'] ?? '') ?></span>
                                            <?php if($comment['updated_at'] && $comment['updated_at'] != $comment['created_at']): ?>
                                            <span class="text-xs text-gray-500">(diedit)</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($comment['user_id'] == $user_id): ?>
                                        <div class="relative">
                                            <button class="text-gray-500 hover:text-white" onclick="toggleCommentDropdown(<?= $comment['id'] ?>)">⋯</button>
                                            <div id="comment-dropdown-<?= $comment['id'] ?>" class="dropdown-menu" style="position: absolute; right: 0; top: 20px;">
                                                <button onclick="editComment(<?= $comment['id'] ?>, `<?= addslashes($comment['comment']) ?>`)">✏️ Edit</button>
                                                <a href="?delete_comment=<?= $comment['id'] ?>" onclick="return confirm('Hapus komentar ini?')">🗑️ Hapus</a>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-content" id="comment-content-<?= $comment['id'] ?>">
                                        <p class="text-sm text-gray-300"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                    </div>
                                    <div class="comment-edit-form hidden mt-2" id="comment-edit-form-<?= $comment['id'] ?>">
                                        <form method="POST">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <textarea name="comment_text" rows="2" class="w-full px-3 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white text-sm"><?= htmlspecialchars($comment['comment']) ?></textarea>
                                            <div class="flex gap-2 mt-2">
                                                <button type="submit" name="edit_comment" class="bg-blue-600 text-white px-2 py-1 rounded text-xs">Simpan</button>
                                                <button type="button" onclick="cancelEditComment(<?= $comment['id'] ?>)" class="bg-gray-600 text-white px-2 py-1 rounded text-xs">Batal</button>
                                            </div>
                                        </form>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1"><?= $comment['time_formatted'] ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="mt-4 relative">
                            <div class="flex gap-2">
                                <div class="flex-1 relative">
                                    <input type="text" id="comment-input-<?= $post['id'] ?>" placeholder="Tulis komentar..." class="w-full px-4 py-2 bg-[#0F172A] border border-[#334155] rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 text-sm">
                                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-400" onclick="toggleStickerPicker(this, <?= $post['id'] ?>)">😊</button>
                                </div>
                                <button type="button" onclick="submitComment(<?= $post['id'] ?>)" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-500 transition font-medium">Kirim</button>
                            </div>
                            <div id="sticker-picker-<?= $post['id'] ?>" class="sticker-picker hidden">
                                <?php foreach($stickers as $sticker): ?>
                                <span onclick="insertSticker(this, '<?= $sticker ?>', <?= $post['id'] ?>)"><?= $sticker ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Preview image before upload
    function previewImage(input) {
        const previewContainer = document.getElementById('imagePreviewContainer');
        const preview = document.getElementById('imagePreview');
        if(input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) { preview.src = e.target.result; previewContainer.classList.remove('hidden'); }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removeImage() {
        document.getElementById('imageInput').value = '';
        document.getElementById('imagePreviewContainer').classList.add('hidden');
        document.getElementById('imagePreview').src = '';
    }
    
    // Sticker picker functions
    function toggleStickerPicker(btn, postId) {
        const picker = document.getElementById('sticker-picker-' + postId);
        document.querySelectorAll('.sticker-picker').forEach(p => { if(p !== picker) p.style.display = 'none'; });
        picker.style.display = picker.style.display === 'flex' ? 'none' : 'flex';
    }
    
    function insertSticker(element, sticker, postId) {
        const input = document.getElementById('comment-input-' + postId);
        input.value = input.value + ' ' + sticker + ' ';
        document.getElementById('sticker-picker-' + postId).style.display = 'none';
    }
    
    $(document).click(function(e) {
        if(!$(e.target).closest('.sticker-picker, .sticker-picker + button').length) {
            $('.sticker-picker').hide();
        }
        if(!$(e.target).closest('.dropdown-menu, .post-actions button, .dropdown-menu + button').length) {
            $('.dropdown-menu').hide();
        }
    });
    
    // Like with AJAX (no refresh)
    $('.like-btn').click(function(e) {
        e.preventDefault();
        const btn = $(this);
        const postId = btn.data('post-id');
        
        $.ajax({
            url: '?ajax_like=' + postId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const likesSpan = btn.find('.likes-count');
                let currentLikes = parseInt(likesSpan.text());
                
                if(response.status === 'liked') {
                    btn.addClass('liked text-red-500');
                    btn.removeClass('text-gray-400');
                    likesSpan.text(currentLikes + 1);
                } else {
                    btn.removeClass('liked text-red-500');
                    btn.addClass('text-gray-400');
                    likesSpan.text(currentLikes - 1);
                }
            }
        });
        return false;
    });
    
    // Submit comment with AJAX (no refresh)
    function submitComment(postId) {
        const commentInput = document.getElementById('comment-input-' + postId);
        const comment = commentInput.value.trim();
        if(comment === '') return;
        
        $.ajax({
            url: '?ajax_comment=1',
            method: 'POST',
            data: { ajax_comment: 1, post_id: postId, comment: comment },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    const commentsList = document.getElementById('comments-list-' + postId);
                    const newCommentHtml = `
                        <div class="flex gap-3 group" id="comment-${response.comment_id}" data-comment-id="${response.comment_id}">
                            <img src="../uploads/profiles/${response.profile_picture}" class="w-8 h-8 rounded-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(response.full_name)}&background=2563EB&color=fff'">
                            <div class="flex-1 bg-[#0F172A] rounded-xl p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-sm text-white">${response.full_name}</span>
                                        <span class="text-xs text-gray-500">${response.position || ''}</span>
                                    </div>
                                    ${response.user_id == response.current_user_id ? `
                                    <div class="relative">
                                        <button class="text-gray-500 hover:text-white" onclick="toggleCommentDropdown(${response.comment_id})">⋯</button>
                                        <div id="comment-dropdown-${response.comment_id}" class="dropdown-menu" style="position: absolute; right: 0; top: 20px; display: none;">
                                            <button onclick="editComment(${response.comment_id}, \`${response.comment.replace(/`/g, '\\`')}\`)">✏️ Edit</button>
                                            <a href="?delete_comment=${response.comment_id}" onclick="return confirm('Hapus komentar ini?')">🗑️ Hapus</a>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                                <div class="comment-content" id="comment-content-${response.comment_id}">
                                    <p class="text-sm text-gray-300">${response.comment}</p>
                                </div>
                                <div class="comment-edit-form hidden mt-2" id="comment-edit-form-${response.comment_id}">
                                    <form method="POST">
                                        <input type="hidden" name="comment_id" value="${response.comment_id}">
                                        <textarea name="comment_text" rows="2" class="w-full px-3 py-2 bg-[#0F172A] border border-[#334155] rounded-lg text-white text-sm">${response.comment.replace(/<br\s*\/?>/gi, '\n').replace(/&lt;/g, '<').replace(/&gt;/g, '>')}</textarea>
                                        <div class="flex gap-2 mt-2">
                                            <button type="submit" name="edit_comment" class="bg-blue-600 text-white px-2 py-1 rounded text-xs">Simpan</button>
                                            <button type="button" onclick="cancelEditComment(${response.comment_id})" class="bg-gray-600 text-white px-2 py-1 rounded text-xs">Batal</button>
                                        </div>
                                    </form>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">${response.time}</p>
                            </div>
                        </div>
                    `;
                    commentsList.insertAdjacentHTML('beforeend', newCommentHtml);
                    
                    // Update comment count
                    const commentCountSpan = $(`#post-${postId} .comment-count`);
                    let currentCount = parseInt(commentCountSpan.text());
                    commentCountSpan.text(currentCount + 1);
                    
                    commentInput.value = '';
                    
                    // Auto scroll to new comment
                    const newComment = document.getElementById('comment-' + response.comment_id);
                    if(newComment) newComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
    
    // Enter to submit comment
    document.querySelectorAll('[id^="comment-input-"]').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const postId = this.id.split('-')[2];
                submitComment(postId);
            }
        });
    });
    
    // Dropdown functions
    function toggleDropdown(postId) { $('#dropdown-' + postId).toggle(); }
    function toggleCommentDropdown(commentId) { $('#comment-dropdown-' + commentId).toggle(); }
    
    function editPost(postId, content) {
        $('#post-content-' + postId).hide();
        $('#edit-form-' + postId).removeClass('hidden');
        $('#dropdown-' + postId).hide();
    }
    
    function cancelEdit(postId) {
        $('#post-content-' + postId).show();
        $('#edit-form-' + postId).addClass('hidden');
    }
    
    function editComment(commentId, content) {
        $('#comment-content-' + commentId).hide();
        $('#comment-edit-form-' + commentId).removeClass('hidden');
        $('#comment-dropdown-' + commentId).hide();
    }
    
    function cancelEditComment(commentId) {
        $('#comment-content-' + commentId).show();
        $('#comment-edit-form-' + commentId).addClass('hidden');
    }
    
    $('.comment-toggle').click(function() {
        const postId = $(this).data('post-id');
        $('#comments-' + postId).slideToggle(200);
    });
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>