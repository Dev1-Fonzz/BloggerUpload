<?php
// lib/BloggerAPI.php - Wrapper untuk Blogger API v3
// Menggunakan cURL untuk request HTTP

class BloggerAPI {
    private $baseUrl = 'https://www.googleapis.com/blogger/v3';
    private $accessToken;
    private $blogId;
    
    public function __construct($accessToken, $blogId) {
        $this->accessToken = $accessToken;
        $this->blogId = $blogId;
    }
    
    // Dapatkan senarai post (draft/published)
    public function getPosts($maxResults = 10, $status = 'draft') {
        $url = "{$this->baseUrl}/blogs/{$this->blogId}/posts";
        $params = http_build_query([
            'status' => $status,
            'fetchBodies' => 'true',
            'maxResults' => $maxResults
        ]);
        
        return $this->request("$url?$params");
    }
    
    // Cari post berdasarkan title/unique ID
    public function findPostByTitle($uniqueId) {
        $posts = $this->getPosts(50);
        if (!isset($posts['items'])) return null;
        
        foreach ($posts['items'] as $post) {
            if (strpos($post['title'], $uniqueId) !== false) {
                return $post;
            }
        }
        return null;
    }
    
    // Extract image URL dari content post
    public function extractImageUrl($content) {
        // Cari tag <img> pertama
        if (preg_match('/<img[^>]+src="([^">]+)"/i', $content, $matches)) {
            $url = $matches[1];
            // Convert ke resolution tinggi (s1600)
            return preg_replace('/\/s[0-9]+(-c)?\//', '/s1600/', $url);
        }
        return null;
    }
    
    // Helper: Buat HTTP request dengan Authorization header
    private function request($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logError("cURL error: $error");
            return null;
        }
        
        if ($httpCode >= 400) {
            $this->logError("API error ($httpCode): $response");
            return null;
        }
        
        return json_decode($response, true);
    }
    
    private function logError($msg) {
        $logFile = __DIR__ . '/../logs/blogger_api_errors.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] $msg\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
?>
?>
