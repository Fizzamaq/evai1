<?php
// classes/UploadHandler.class.php

class UploadHandler {
    private $pdo; // Optional: if you need PDO for logging uploads to DB

    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }

    /**
     * Handles the upload of a single file.
     *
     * @param array $file The $_FILES array entry for the uploaded file (e.g., $_FILES['picture_upload']).
     * @param string $targetDirectory The directory where the file should be moved (e.g., '../assets/uploads/bookings/').
     * @param array $allowedMimeTypes An array of allowed MIME types (e.g., ['image/jpeg', 'image/png']).
     * @param int $maxFileSize The maximum allowed file size in bytes.
     * @return string|false The new filename on success, or false on failure.
     */
    public function uploadFile(array $file, string $targetDirectory, array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], int $maxFileSize = 5 * 1024 * 1024) { // Default 5MB
        // Ensure target directory exists and is writable
        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0775, true)) { // 0775 for directory permissions
                error_log("UploadHandler Error: Target directory does not exist and could not be created: " . $targetDirectory);
                $_SESSION['upload_error'] = "Upload directory not found or not writable.";
                return false;
            }
        }
        if (!is_writable($targetDirectory)) {
            error_log("UploadHandler Error: Target directory is not writable: " . $targetDirectory);
            $_SESSION['upload_error'] = "Upload directory is not writable.";
            return false;
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = "File upload error: ";
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage .= "File is too large. Max size allowed is " . ($maxFileSize / (1024 * 1024)) . "MB.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage .= "File was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessage .= "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage .= "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage .= "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage .= "A PHP extension stopped the file upload.";
                    break;
                default:
                    $errorMessage .= "Unknown upload error.";
            }
            error_log("UploadHandler Error: " . $errorMessage . " (Code: " . $file['error'] . ")");
            $_SESSION['upload_error'] = $errorMessage;
            return false;
        }

        // Validate file size
        if ($file['size'] > $maxFileSize) {
            error_log("UploadHandler Error: File exceeds maximum allowed size. File: " . $file['name'] . ", Size: " . $file['size'] . " bytes.");
            $_SESSION['upload_error'] = "File is too large. Max size allowed is " . ($maxFileSize / (1024 * 1024)) . "MB.";
            return false;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            error_log("UploadHandler Error: Invalid file type. File: " . $file['name'] . ", MIME: " . $mimeType . ".");
            $_SESSION['upload_error'] = "Invalid file type. Only images (JPG, PNG, GIF, WebP) are allowed.";
            return false;
        }

        // Generate a unique filename to prevent collisions
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = uniqid('upload_', true) . '.' . $extension;
        $targetPath = rtrim($targetDirectory, '/') . '/' . $newFilename;

        // Move the uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Success
            $_SESSION['upload_error'] = null; // Clear any previous upload errors
            return $newFilename;
        } else {
            error_log("UploadHandler Error: Failed to move uploaded file from " . $file['tmp_name'] . " to " . $targetPath);
            $_SESSION['upload_error'] = "Failed to move uploaded file. Check server permissions.";
            return false;
        }
    }
}
