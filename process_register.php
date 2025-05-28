<?php
require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['register_error'] = "Invalid request method";
    header("Location: register.php");
    exit();
}

try {
    $required = ['first_name', 'last_name', 'email', 'password', 'user_type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in all required fields");
        }
    }

    $data = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
        'password' => $_POST['password'],
        'user_type' => (int)$_POST['user_type']
    ];

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    if (strlen($data['password']) < 8) {
        throw new Exception("Password must be at least 8 characters");
    }

    require_once '../classes/User.class.php';
    $user = new User($pdo);
    $userId = $user->register($data['email'], $data['password'], $data['first_name'], $data['last_name'], $data['user_type']);

    $_SESSION['registration_success'] = "Registration successful! Please login.";
    header("Location: login.php");
    exit();

} catch (Exception $e) {
    $_SESSION['register_error'] = $e->getMessage();
    header("Location: register.php");
    exit();
}