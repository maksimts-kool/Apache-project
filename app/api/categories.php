<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php';
require_once '../categories.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $categories = getAllCategories($pdo);
    $categories = array_map(static function (array $category): array {
        $category['label'] = buildCategoryLabel($category);
        return $category;
    }, $categories);

    echo json_encode(['success' => true, 'categories' => $categories]);
    exit;
}

if ($method === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    try {
        $category = createCustomCategory(
            $pdo,
            (string) ($input['emoji'] ?? ''),
            (string) ($input['name'] ?? ''),
            (int) $_SESSION['user_id']
        );

        echo json_encode(['success' => true, 'category' => $category]);
    } catch (InvalidArgumentException | RuntimeException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create category.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);