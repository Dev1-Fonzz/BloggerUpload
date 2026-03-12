<?php
// config.php - Load configuration dari .env file
// Jangan ubah kod ini kecuali anda tahu apa anda buat

if (!file_exists(__DIR__ . '/.env')) {
    die('❌ File .env tidak ditemui. Sila setup dahulu.');
}

// Baca dan parse file .env
$envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($envLines as $line) {
    if (strpos(trim($line), '#') === 0) continue; // Skip comment
    if (strpos($line, '=') === false) continue;
    
    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim(trim($value), '"\''); // Buang quotes jika ada
    putenv("$key=$value");
    $_ENV[$key] = $value;
}

// Helper function untuk dapat config
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false ? $default : $value;
}

// Validate required config
$required = ['BLOGGER_EMAIL', 'SMTP_EMAIL', 'SMTP_APP_PASSWORD', 'BLOG_ID', 'ACCESS_TOKEN'];
foreach ($required as $req) {
    if (!env($req)) {
        die("❌ Config '$req' tidak ditetapkan dalam .env");
    }
}

// Set timezone & error reporting
date_default_timezone_set('Asia/Kuala_Lumpur');
if (env('DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Auto-create logs folder
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
?>
