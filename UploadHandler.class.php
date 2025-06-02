<?php
class UploadHandler {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxSize = 2 * 1024 * 1024; // 2MB
    private $uploadDir = __DIR__ . '/../assets/uploads/';

    public function handleUpload($file, $subfolder = '') {
        $targetDir = $this->uploadDir . $subfolder;
        
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
        }

        if ($file['size'] > $this->maxSize) {
            throw new Exception("File too large. Maximum size is 2MB.");
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $targetPath = $targetDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to upload file.");
        }

        return $filename;
    }

    public function deleteFile($filename, $subfolder = '') {
        $filePath = $this->uploadDir . $subfolder . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }
        return false;
    }
}