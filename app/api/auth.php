<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$action = $_REQUEST['action'] ?? '';
$endpoint = '/api/auth.php';

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        api_log_validation_error($endpoint, 'register', 'Registration validation failed', [
            'username_provided' => !empty($username),
            'email_provided' => !empty($email),
            'password_provided' => !empty($password),
        ]);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            api_log_validation_error($endpoint, 'register', 'Registration rejected: existing user', [
                'username' => $username,
            ]);
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword]);
        api_log('INFO', $endpoint, 'register', 'User registration succeeded', [
            'username' => $username,
        ]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        api_log_db_error($endpoint, 'register', $e, [
            'username' => $username,
        ]);
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        api_log_validation_error($endpoint, 'login', 'Login validation failed', [
            'email_provided' => !empty($email),
            'password_provided' => !empty($password),
        ]);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    api_log('INFO', $endpoint, 'login', 'User login attempt', [
        'email_hash' => sha1(strtolower($email)),
    ]);

    try {
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            api_log('INFO', $endpoint, 'login', 'User login succeeded', [
                'user_id' => (int) $user['id'],
            ]);
            echo json_encode(['success' => true]);
        } else {
            api_log('WARNING', $endpoint, 'login', 'User login failed', [
                'email_hash' => sha1(strtolower($email)),
            ]);
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        }
    } catch (PDOException $e) {
        api_log_db_error($endpoint, 'login', $e, [
            'email_hash' => sha1(strtolower($email)),
        ]);
        echo json_encode(['success' => false, 'message' => 'Login failed due to database error']);
    }
    exit;
}

if ($action === 'logout') {
    api_log('INFO', $endpoint, 'logout', 'User logout', [
        'had_session' => isset($_SESSION['user_id']),
    ]);
    session_destroy();
    header("Location: /");
    exit;
}

api_log_validation_error($endpoint, 'unknown', 'Invalid auth action', ['action' => $action]);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
