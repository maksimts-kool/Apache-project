<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$q = $_GET['q'] ?? '';
$q = ltrim($q, '#');

if (empty($q)) {
    echo json_encode(['tags' => []]);
    exit;
}

// Fetch all tags column values
// For better performance, we only fetch rows where tags LIKE ?
$stmt = $pdo->prepare("SELECT DISTINCT tags FROM emojis WHERE tags LIKE ?");
$stmt->execute(['%' . $q . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

$allTags = [];
foreach ($rows as $row) {
    if (!$row) continue;
    $tags = explode(',', $row);
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (stripos($tag, $q) === 0 && !in_array($tag, $allTags)) {
            $allTags[] = $tag;
        }
    }
}

// Sort alphabetically and take top 3
sort($allTags);
$allTags = array_slice($allTags, 0, 3);

echo json_encode(['tags' => $allTags]);
