<?php
// upload.php - Ringkas untuk mfdstore-private@blogger.com
error_reporting(0);
header('Content-Type: application/json');

// 🔧 🔥 UBAH 4 BENDA NI SAHAJA 🔥 🔧
$bloggerEmail = 'mfdstore-private@blogger.com';  // ✅ Email Blogger (sudah betul)
$smtpEmail    = 'pfareezonzz01@gmail.com';        // 🔧 UBAH: Gmail anda
$smtpPassword = 'aflp hnvn mcjj yxpa';            // 🔧 UBAH: App Password 16-digit
$blogId       = '357975693813797199';          // 🔧 UBAH: Blog ID dari Blogger
$accessToken  = 'ya29.a0ATkoCc4zTrRArHSYmnv-c9jgd4wLVoMzYUfbvnnaByV5bX2C3FOgZHS78qQl6KYo6CR4DozFLrLNw5svhClUPB7qJYAiHa_rKH-4IYjjvdwTKw9Fpo_eXRHnNs3vItq9v978_Gss1PjvDzZb1h70CW0Is50lRW8wYiupZDc9pPJRh7g9mzHvjawo0UNoi34l1uVraPsaCgYKAesSARQSFQHGX2MiHiaL8JDkMg5qss7qFU_Fzw0206';                  // 🔧 UBAH: Access Token dari OAuth Playground
// 🔧 🔥 AKHIR UBAHAN 🔥 🔧

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}

$bloggerEmail = $_POST['bloggerEmail'] ?? $bloggerEmail;
$smtpEmail    = $_POST['smtpEmail'] ?? $smtpEmail;
$smtpPassword = $_POST['smtpPassword'] ?? $smtpPassword;
$image = $_FILES['image'] ?? null;

if (!$image || $image['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'Fail gambar tidak valid']); exit;
}

$uniqueId = 'IMG_' . time() . '_' . rand(1000,9999);

// SMTP Send
$socket = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 30);
if (!$socket) { echo json_encode(['success'=>false,'message'=>'Cannot connect to SMTP']); exit; }

fgets($socket);
fwrite($socket, "EHLO " . gethostname() . "\r\n"); fgets($socket);
fwrite($socket, "STARTTLS\r\n"); fgets($socket);
stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
fwrite($socket, "EHLO " . gethostname() . "\r\n"); fgets($socket);
fwrite($socket, "AUTH LOGIN\r\n"); fgets($socket);
fwrite($socket, base64_encode($smtpEmail) . "\r\n"); fgets($socket);
fwrite($socket, base64_encode($smtpPassword) . "\r\n"); fgets($socket);
fwrite($socket, "MAIL FROM:<" . $smtpEmail . ">\r\n"); fgets($socket);
fwrite($socket, "RCPT TO:<" . $bloggerEmail . ">\r\n"); fgets($socket);
fwrite($socket, "DATA\r\n"); fgets($socket);

$boundary = '----=' . md5(uniqid());
$headers = "From: <$smtpEmail>\r\nTo: <$bloggerEmail>\r\nSubject: $uniqueId\r\n";
$headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";

$message = "--$boundary\r\nContent-Type: text/plain\r\n\r\n$uniqueId\r\n\r\n";
$fileContent = file_get_contents($image['tmp_name']);
$encoded = chunk_split(base64_encode($fileContent));
$message .= "--$boundary\r\nContent-Type: application/octet-stream; name=\"{$image['name']}\"\r\n";
$message .= "Content-Disposition: attachment; filename=\"{$image['name']}\"\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n$encoded\r\n\r\n--$boundary--\r\n.\r\n";

fwrite($socket, $headers . $message); fgets($socket);
fwrite($socket, "QUIT\r\n"); fclose($socket);

sleep(20); // Tunggu Blogger process

// Get image URL from Blogger API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/blogger/v3/blogs/{$blogId}/posts?status=draft&fetchBodies=true&maxResults=10");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
$response = curl_exec($ch); curl_close($ch);

$data = json_decode($response, true); $imageUrl = null;

if (isset($data['items'])) {
    foreach ($data['items'] as $post) {
        if (strpos($post['title'], $uniqueId) !== false) {
            preg_match('/<img[^>]+src="([^">]+)"/i', $post['content'], $matches);
            if (isset($matches[1])) {
                $imageUrl = preg_replace('/\/s[0-9]+\//', '/s1600/', $matches[1]);
                break;
            }
        }
    }
}

if ($imageUrl) {
    echo json_encode(['success'=>true, 'image_url'=>$imageUrl]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Gagal dapatkan link. Check Token & Blog ID']);
}
?>
