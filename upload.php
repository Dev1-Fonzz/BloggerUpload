<?php
// upload.php - Main handler untuk upload gambar ke Blogger
// ✅ Modular, secure, dan ada logging

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/SimpleSMTP.php';
require_once __DIR__ . '/lib/BloggerAPI.php';

// 🔧 Helper: Return JSON error & log
function sendError($msg, $log = true) {
    if ($log) {
        $logFile = __DIR__ . '/logs/upload_errors.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] ERROR: $msg\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// 🔧 Helper: Return JSON success
function sendSuccess($imageUrl) {
    echo json_encode(['success' => true, 'image_url' => $imageUrl]);
    exit;
}

// 🔧 Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method tidak dibenarkan. Gunakan POST.', false);
}

// 🔧 Ambil data dari POST + FILES
$bloggerEmail = $_POST['bloggerEmail'] ?? '';
$smtpEmail    = $_POST['smtpEmail'] ?? '';
$smtpPassword = $_POST['smtpPassword'] ?? '';
$image        = $_FILES['image'] ?? null;

// 🔧 Validasi data asas
if (!$bloggerEmail || !$smtpEmail || !$smtpPassword || !$image) {
    sendError('Data tidak lengkap. Sila isi semua field.');
}

if ($image['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Saiz fail melebihi had server',
        UPLOAD_ERR_FORM_SIZE => 'Saiz fail melebihi had form',
        UPLOAD_ERR_PARTIAL => 'Upload tidak lengkap',
        UPLOAD_ERR_NO_FILE => 'Tiada fail dipilih',
    ];
    sendError('Error upload: ' . ($errors[$image['error']] ?? 'Unknown'));
}
// 🔧 Validasi jenis & saiz fail
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($image['type'], $allowedTypes)) {
    sendError('Format gambar tidak disokong. Gunakan JPG/PNG/GIF/WebP.');
}

if ($image['size'] > 10 * 1024 * 1024) { // 10MB
    sendError('Saiz gambar melebihi 10MB.');
}

// 🔧 Generate unique ID untuk track post ini
$uniqueId = 'IMG_' . time() . '_' . bin2hex(random_bytes(4));

// 🔧 Step 1: Hantar email ke Blogger via SMTP
try {
    $mailer = new SimpleSMTP($smtpEmail, $smtpPassword);
    $sent = $mailer->send($bloggerEmail, $uniqueId, 'Auto Upload - ' . $uniqueId, [
        'tmp_name' => $image['tmp_name'],
        'name' => $image['name']
    ]);
    
    if (!$sent) {
        sendError('Gagal hantar email. Semak: 1) App Password, 2) 2FA Gmail, 3) Internet server.');
    }
} catch (Exception $e) {
    sendError('SMTP Error: ' . $e->getMessage());
}

// 🔧 Step 2: Tunggu Blogger proses email (biasanya 10-30 saat)
// ⚠️ Untuk production, guna webhook atau queue system
sleep(20);

// 🔧 Step 3: Cari post & extract image URL via Blogger API
try {
    $api = new BloggerAPI(env('ACCESS_TOKEN'), env('BLOG_ID'));
    $post = $api->findPostByTitle($uniqueId);
    
    if (!$post) {
        sendError('Post tidak dijumpai dalam Blogger. Pastikan: 1) Email sampai, 2) Blog ID betul, 3) Token masih valid.');
    }
    
    $imageUrl = $api->extractImageUrl($post['content']);
    if (!$imageUrl) {
        sendError('Gagal extract URL gambar dari post. Check content post di Blogger.');
    }
    
    // 🔧 Success! Return URL
    sendSuccess($imageUrl);
    } catch (Exception $e) {
    sendError('Blogger API Error: ' . $e->getMessage());
}
?>
