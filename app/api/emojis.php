<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Check if it's a LIKE action
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action']) && $input['action'] === 'like') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE emojis SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("SELECT likes FROM emojis WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        echo json_encode(['success' => true, 'likes' => $row['likes'] ?? 0]);
        exit;
    }

    // Otherwise, it's a CREATE action
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $symbol = $_POST['symbol'] ?? '';
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
    $symbol = $input['symbol'] ?? '';
    $name = $input['name'] ?? '';
    $category = $input['category'] ?? '';
    $tags = $input['tags'] ?? '';
    $description = $input['description'] ?? '';
    $is_anonymous = isset($input['is_anonymous']) ? (int)$input['is_anonymous'] : 0;

    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM emojis WHERE id = ?");
    $stmt->execute([$id]);
    $emoji = $stmt->fetch();

    if (!$emoji || $emoji['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized or not found']);
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
