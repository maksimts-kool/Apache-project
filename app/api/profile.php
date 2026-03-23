<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = $_GET['tab'] ?? 'emojis';

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Get user info
$stmt = $pdo->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id;

if ($tab === 'favorites') {
    // Only the owner can see favorites
    if (!$is_owner) {
        echo json_encode(['success' => false, 'message' => 'Favorites are private']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT e.*, u.username
        FROM emoji_likes el
        JOIN emojis e ON el.emoji_id = e.id
        LEFT JOIN users u ON e.user_id = u.id
        WHERE el.user_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$user_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT e.*, u.username
        FROM emojis e
        LEFT JOIN users u ON e.user_id = u.id
        WHERE e.user_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$user_id]);
}

$emojis = $stmt->fetchAll();

// Add user_liked flag
if (isset($_SESSION['user_id'])) {
    $likeStmt = $pdo->prepare("SELECT emoji_id FROM emoji_likes WHERE user_id = ?");
    $likeStmt->execute([$_SESSION['user_id']]);
    $likedIds = array_column($likeStmt->fetchAll(), 'emoji_id');
    foreach ($emojis as &$emoji) {
        $emoji['user_liked'] = in_array($emoji['id'], $likedIds);
    }
    unset($emoji);
}

echo json_encode([
    'success' => true,
    'user' => $user,
    'emojis' => $emojis,
    'total' => count($emojis),
    'is_owner' => $is_owner
]);
