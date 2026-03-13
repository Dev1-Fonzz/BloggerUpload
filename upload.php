<?php
// upload.php - FIXED VERSION (selalu return JSON)
// 🔧 Untuk: mfdstore-private@blogger.com

// ✅ Pastikan NO whitespace sebelum <?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// 🔧 Helper: Return JSON error (selalu valid JSON)
function sendJSON($success, $message, $data = []) {
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) $response['debug'] = $data;
    echo json_encode($response);
    exit;
}

// 🔧 Config - UBAH 4 BENDA NI SAHAJA
$config = [
    'bloggerEmail' => 'mfdstore-private@blogger.com',
    'smtpEmail'    => 'pfareezonzz01@gmail.com',      // 🔧 UBAH
    'smtpPassword' => 'aflp hnvn mcjj yxpa',          // 🔧 UBAH: App Password
    'blogId'       => '357975693813797199',        // 🔧 UBAH
    'accessToken'  => 'ya29.a0ATkoCc4zTrRArHSYmnv-c9jgd4wLVoMzYUfbvnnaByV5bX2C3FOgZHS78qQl6KYo6CR4DozFLrLNw5svhClUPB7qJYAiHa_rKH-4IYjjvdwTKw9Fpo_eXRHnNs3vItq9v978_Gss1PjvDzZb1h70CW0Is50lRW8wYiupZDc9pPJRh7g9mzHvjawo0UNoi34l1uVraPsaCgYKAesSARQSFQHGX2MiHiaL8JDkMg5qss7qFU_Fzw0206'                 // 🔧 UBAH: Access Token
];

// ✅ Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(false, 'Method not allowed. Use POST.');
}

// ✅ Ambil data
$bloggerEmail = $_POST['bloggerEmail'] ?? $config['bloggerEmail'];
$smtpEmail    = $_POST['smtpEmail'] ?? $config['smtpEmail'];
$smtpPassword = $_POST['smtpPassword'] ?? $config['smtpPassword'];
$image = $_FILES['image'] ?? null;

// ✅ Validasi
if (!$image) {
    sendJSON(false, 'No image file received');
}
if ($image['error'] !== UPLOAD_ERR_OK) {
    $errors = [1=>'File too large', 2=>'File too large', 3=>'Partial upload', 4=>'No file', 6=>'Missing temp folder', 7=>'Cannot write', 8=>'Extension stopped'];
    sendJSON(false, 'Upload error: ' . ($errors[$image['error']] ?? 'Code '.$image['error']));
}
if (!in_array($image['type'], ['image/jpeg','image/png','image/gif','image/webp'])) {
    sendJSON(false, 'Invalid image type: '.$image['type']);
}

$uniqueId = 'IMG_' . time() . '_' . rand(1000,9999);
// ✅ Step 1: SMTP Send
$socket = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 30);
if (!$socket) {
    sendJSON(false, 'SMTP connect failed: '.$errstr, ['errno'=>$errno]);
}
stream_set_timeout($socket, 30);

$smtpSend = function($cmd) use ($socket) { fwrite($socket, $cmd."\r\n"); return fgets($socket, 512); };
$smtpRead = function() use ($socket) { return fgets($socket, 512); };

$smtpSend("EHLO ".gethostname());
$smtpSend("STARTTLS");
if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    sendJSON(false, 'TLS enable failed');
}
$smtpSend("EHLO ".gethostname());
$smtpSend("AUTH LOGIN");
$smtpSend(base64_encode($smtpEmail));
$authResp = $smtpSend(base64_encode($smtpPassword));

if (strpos($authResp, '235') === false) {
    sendJSON(false, 'SMTP Auth failed. Check App Password & 2FA', ['response'=>trim($authResp)]);
}

$smtpSend("MAIL FROM:<$smtpEmail>");
$smtpSend("RCPT TO:<$bloggerEmail>");
$smtpSend("DATA");

$boundary = '----='.md5(uniqid());
$headers = "From: <$smtpEmail>\r\nTo: <$bloggerEmail>\r\nSubject: $uniqueId\r\n";
$headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";

$message = "--$boundary\r\nContent-Type: text/plain\r\n\r\n$uniqueId\r\n\r\n";
$fileContent = @file_get_contents($image['tmp_name']);
if ($fileContent === false) {
    sendJSON(false, 'Cannot read uploaded file');
}
$encoded = chunk_split(base64_encode($fileContent));
$message .= "--$boundary\r\nContent-Type: application/octet-stream; name=\"{$image['name']}\"\r\n";
$message .= "Content-Disposition: attachment; filename=\"{$image['name']}\"\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n$encoded\r\n\r\n--$boundary--\r\n.\r\n";

$smtpSend($headers . $message);
$smtpSend("QUIT");
fclose($socket);

// ✅ Step 2: Tunggu Blogger
sleep(20);
// ✅ Step 3: Blogger API
if (!function_exists('curl_init')) {
    sendJSON(false, 'cURL not enabled on server');
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://www.googleapis.com/blogger/v3/blogs/{$config['blogId']}/posts?status=draft&fetchBodies=true&maxResults=10",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $config['accessToken']],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    sendJSON(false, 'cURL error: '.$curlError);
}
if ($httpCode >= 400) {
    sendJSON(false, 'Blogger API error (HTTP '.$httpCode.')', ['response'=>substr($response,0,200)]);
}

$data = @json_decode($response, true);
if (!$data || !isset($data['items'])) {
    sendJSON(false, 'Invalid API response', ['response'=>substr($response,0,200)]);
}

$imageUrl = null;
foreach ($data['items'] as $post) {
    if (strpos($post['title'] ?? '', $uniqueId) !== false) {
        if (preg_match('/<img[^>]+src="([^">]+)"/i', $post['content'] ?? '', $matches)) {
            $imageUrl = preg_replace('/\/s[0-9]+(-c)?\//', '/s1600/', $matches[1]);
            break;
        }
    }
}

if ($imageUrl) {
    sendJSON(true, 'Success', ['image_url' => $imageUrl]);
} else {
    sendJSON(false, 'Image not found in posts. Check: 1) Email received by Blogger, 2) Access Token valid, 3) Blog ID correct');
}
?>
