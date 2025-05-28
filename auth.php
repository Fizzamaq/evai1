<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit();
    }
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] == 3; // Assuming 3 is admin type
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}