<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = $_GET['tab'] ?? 'emojis';
$endpoint = '/api/profile.php';

if ($user_id <= 0) {
    api_log_validation_error($endpoint, 'fetch_profile', 'Invalid profile user id', [
        'user_id' => $user_id,
        'tab' => $tab,
    ]);
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        api_log_validation_error($endpoint, 'fetch_profile', 'Profile user not found', [
            'user_id' => $user_id,
            'tab' => $tab,
        ]);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id;

    if ($tab === 'favorites') {
        // Only the owner can see favorites
        if (!$is_owner) {
            api_log_validation_error($endpoint, 'fetch_profile', 'Favorites tab access denied', [
                'user_id' => $user_id,
                'viewer_id' => $_SESSION['user_id'] ?? null,
            ]);
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

    api_log('INFO', $endpoint, 'fetch_profile', 'Profile fetch succeeded', [
        'user_id' => $user_id,
        'tab' => $tab,
        'total' => count($emojis),
    ]);

    echo json_encode([
        'success' => true,
        'user' => $user,
        'emojis' => $emojis,
        'total' => count($emojis),
        'is_owner' => $is_owner
    ]);
} catch (PDOException $e) {
    api_log_db_error($endpoint, 'fetch_profile', $e, [
        'user_id' => $user_id,
        'tab' => $tab,
    ]);

    echo json_encode(['success' => false, 'message' => 'Failed to fetch profile data']);
}
