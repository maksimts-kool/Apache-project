<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php';
require_once '../categories.php';

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = '/api/categories.php';

if ($method === 'GET') {
    try {
        $categories = getAllCategories($pdo);
        $categories = array_map(static function (array $category): array {
            $category['label'] = buildCategoryLabel($category);
            return $category;
        }, $categories);

        api_log('INFO', $endpoint, 'list_categories', 'Categories fetched', [
            'total' => count($categories),
        ]);

        echo json_encode(['success' => true, 'categories' => $categories]);
    } catch (PDOException $e) {
        api_log_db_error($endpoint, 'list_categories', $e);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch categories.']);
    }
    exit;
}

if ($method === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        api_log_validation_error($endpoint, 'create_category', 'Unauthorized category create attempt');
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

        api_log('INFO', $endpoint, 'create_category', 'Custom category created', [
            'user_id' => (int) $_SESSION['user_id'],
            'category_id' => $category['id'] ?? null,
            'name' => $category['name'] ?? null,
        ]);

        echo json_encode(['success' => true, 'category' => $category]);
    } catch (InvalidArgumentException | RuntimeException $e) {
        api_log_validation_error($endpoint, 'create_category', 'Category validation failed', [
            'user_id' => (int) $_SESSION['user_id'],
            'error' => $e->getMessage(),
        ]);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (PDOException $e) {
        api_log_db_error($endpoint, 'create_category', $e, [
            'user_id' => (int) $_SESSION['user_id'],
        ]);
        echo json_encode(['success' => false, 'message' => 'Failed to create category.']);
    }
    exit;
}

api_log_validation_error($endpoint, 'invalid_method', 'Invalid categories method', ['method' => $method]);
echo json_encode(['success' => false, 'message' => 'Invalid method']);