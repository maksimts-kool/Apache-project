<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$q = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'popular';

$sql = "SELECT e.*, u.username FROM emojis e LEFT JOIN users u ON e.user_id = u.id WHERE 1=1";
$params = [];

if (!empty($q)) {
    $sql .= " AND (e.name LIKE ? OR e.tags LIKE ? OR e.symbol LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if (!empty($category)) {
    $sql .= " AND e.category = ?";
    $params[] = $category;
}

switch ($sort) {
    case 'new':
        $sql .= " ORDER BY e.created_at DESC";
        break;
    case 'alpha':
        $sql .= " ORDER BY e.name ASC";
        break;
    case 'popular':
    default:
        $sql .= " ORDER BY e.likes DESC, e.downloads DESC, e.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$emojis = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'emojis' => $emojis,
    'total' => count($emojis)
]);
