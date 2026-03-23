<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';
require_once '../categories.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Check if it's a LIKE action
    $input = json_decode(file_get_contents('php://input'), true);
    // COPY action — increment downloads
    if (isset($input['action']) && $input['action'] === 'copy') {
        $id = (int)($input['id'] ?? 0);
        if ($id > 0) {
            $copiedEmojiIds = [];
            if (isset($_COOKIE['copied_emojis'])) {
                $decodedCookie = json_decode($_COOKIE['copied_emojis'], true);
                if (is_array($decodedCookie)) {
                    $copiedEmojiIds = array_values(array_unique(array_map('intval', $decodedCookie)));
                }
            }

            $alreadyCounted = in_array($id, $copiedEmojiIds, true);

            if (!$alreadyCounted) {
                $pdo->prepare("UPDATE emojis SET downloads = downloads + 1 WHERE id = ?")->execute([$id]);
                $copiedEmojiIds[] = $id;

                $cookieValue = json_encode(array_values(array_unique($copiedEmojiIds)));
                setcookie('copied_emojis', $cookieValue, time() + 31536000, '/');
                $_COOKIE['copied_emojis'] = $cookieValue;
            }

            $stmt = $pdo->prepare("SELECT downloads FROM emojis WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            echo json_encode([
                'success' => true,
                'downloads' => $row['downloads'] ?? 0,
                'counted' => !$alreadyCounted,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid id']);
        }
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'like') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Login required', 'auth' => false]);
            exit;
        }
        $id = $input['id'] ?? 0;
        $uid = $_SESSION['user_id'];

        // Check if already liked
        $check = $pdo->prepare("SELECT 1 FROM emoji_likes WHERE user_id = ? AND emoji_id = ?");
        $check->execute([$uid, $id]);

        if ($check->fetch()) {
            // Unlike
            $pdo->prepare("DELETE FROM emoji_likes WHERE user_id = ? AND emoji_id = ?")->execute([$uid, $id]);
            $pdo->prepare("UPDATE emojis SET likes = likes - 1 WHERE id = ? AND likes > 0")->execute([$id]);
            $liked = false;
        } else {
            // Like
            $pdo->prepare("INSERT INTO emoji_likes (user_id, emoji_id) VALUES (?, ?)")->execute([$uid, $id]);
            $pdo->prepare("UPDATE emojis SET likes = likes + 1 WHERE id = ?")->execute([$id]);
            $liked = true;
        }

        $stmt = $pdo->prepare("SELECT likes FROM emojis WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        echo json_encode(['success' => true, 'likes' => $row['likes'] ?? 0, 'liked' => $liked]);
        exit;
    }

    // Otherwise, it's a CREATE action
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $symbol = trim($_POST['symbol'] ?? '');
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];

    if (empty($symbol) || empty($name) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    if (!categoryExists($pdo, $category)) {
        echo json_encode(['success' => false, 'message' => 'Selected category does not exist']);
        exit;
    }

    // Prevent uploading the same emoji symbol multiple times.
    $dupCheck = $pdo->prepare("SELECT id FROM emojis WHERE symbol = ? LIMIT 1");
    $dupCheck->execute([$symbol]);
    if ($dupCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This emoji already exists']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO emojis (symbol, name, category, tags, description, is_anonymous, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$symbol, $name, $category, $tags, $description, $is_anonymous, $user_id]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $symbol = trim($input['symbol'] ?? '');
    $name = $input['name'] ?? '';
    $category = $input['category'] ?? '';
    $tags = $input['tags'] ?? '';
    $description = $input['description'] ?? '';
    $is_anonymous = isset($input['is_anonymous']) ? (int)$input['is_anonymous'] : 0;

    if (empty($symbol) || empty($name) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    if (!categoryExists($pdo, $category)) {
        echo json_encode(['success' => false, 'message' => 'Selected category does not exist']);
        exit;
    }

    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM emojis WHERE id = ?");
    $stmt->execute([$id]);
    $emoji = $stmt->fetch();

    if (!$emoji || $emoji['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized or not found']);
        exit;
    }

    // Prevent updating to a symbol that already exists on another emoji.
    $dupCheck = $pdo->prepare("SELECT id FROM emojis WHERE symbol = ? AND id <> ? LIMIT 1");
    $dupCheck->execute([$symbol, $id]);
    if ($dupCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This emoji already exists']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE emojis SET symbol = ?, name = ?, category = ?, tags = ?, description = ?, is_anonymous = ? WHERE id = ?");
        $stmt->execute([$symbol, $name, $category, $tags, $description, $is_anonymous, $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM emojis WHERE id = ?");
    $stmt->execute([$id]);
    $emoji = $stmt->fetch();

    if (!$emoji || $emoji['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized or not found']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM emojis WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);
