<?php
// lib/SimpleSMTP.php - Simple SMTP Mailer untuk Gmail
// Menggunakan socket connection, tanpa dependency luar

class SimpleSMTP {
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 587;
    private $username;
    private $password;
    private $timeout = 30;
    
    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
    
    public function send($to, $subject, $body, $attachment = null) {
        $socket = @fsockopen($this->smtp_host, $this->smtp_port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            $this->logError("Connection failed: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($socket, $this->timeout);
        
        if (!$this->expect($socket, 220)) return $this->fail($socket, 'HELO');
        $this->send($socket, "EHLO " . gethostname() . "\r\n");
        if (!$this->expect($socket, 250)) return $this->fail($socket, 'EHLO');
        
        $this->send($socket, "STARTTLS\r\n");
        if (!$this->expect($socket, 220)) return $this->fail($socket, 'STARTTLS');
        
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $this->fail($socket, 'TLS enable failed');
        }
        
        $this->send($socket, "EHLO " . gethostname() . "\r\n");
        if (!$this->expect($socket, 250)) return $this->fail($socket, 'EHLO after TLS');
        
        $this->send($socket, "AUTH LOGIN\r\n");
        if (!$this->expect($socket, 334)) return $this->fail($socket, 'AUTH LOGIN');
        
        $this->send($socket, base64_encode($this->username) . "\r\n");
        if (!$this->expect($socket, 334)) return $this->fail($socket, 'Username');
        
        $this->send($socket, base64_encode($this->password) . "\r\n");
        if (!$this->expect($socket, 235)) return $this->fail($socket, 'Password - Check App Password!');
        
        $this->send($socket, "MAIL FROM:<" . $this->username . ">\r\n");
        if (!$this->expect($socket, 250)) return $this->fail($socket, 'MAIL FROM');
                $this->send($socket, "RCPT TO:<" . $to . ">\r\n");
        if (!$this->expect($socket, 250)) return $this->fail($socket, 'RCPT TO');
        
        $this->send($socket, "DATA\r\n");
        if (!$this->expect($socket, 354)) return $this->fail($socket, 'DATA');
        
        // Build MIME message
        $boundary = '----=' . md5(uniqid(time(), true));
        $headers = "From: <" . $this->username . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
        
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=utf-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";
        
        if ($attachment && file_exists($attachment['tmp_name'])) {
            $fileContent = file_get_contents($attachment['tmp_name']);
            $encoded = chunk_split(base64_encode($fileContent));
            $filename = basename($attachment['name']);
            
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
            $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= $encoded . "\r\n\r\n";
        }
        
        $message .= "--$boundary--\r\n.\r\n";
        $this->send($socket, $message);
        
        if (!$this->expect($socket, 250)) return $this->fail($socket, 'Message send');
        
        $this->send($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    }
    
    private function send($socket, $data) {
        fwrite($socket, $data);
        return true;
    }
    
    private function expect($socket, $code) {
        $response = fgets($socket, 1024);
        return $response && strpos($response, (string)$code) === 0;
    }    
    private function fail($socket, $step) {
        $this->logError("SMTP failed at step: $step");
        @fclose($socket);
        return false;
    }
    
    private function logError($msg) {
        $logFile = __DIR__ . '/../logs/smtp_errors.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] $msg\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
?>
