<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$q = $_GET['q'] ?? '';
$q = ltrim($q, '#');
$endpoint = '/api/tags.php';

if (empty($q)) {
    api_log_validation_error($endpoint, 'tags_search', 'Empty tag query');
    echo json_encode(['tags' => []]);
    exit;
}

api_log('INFO', $endpoint, 'tags_search', 'Tag search request received', [
    'q' => $q,
]);

try {
    // Fetch all tags column values
    // For better performance, we only fetch rows where tags LIKE ?
    $stmt = $pdo->prepare("SELECT DISTINCT tags FROM emojis WHERE tags LIKE ?");
    $stmt->execute(['%' . $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allTags = [];
    foreach ($rows as $row) {
        if (!$row) {
            continue;
        }

        $tags = explode(',', $row);
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (stripos($tag, $q) === 0 && !in_array($tag, $allTags, true)) {
                $allTags[] = $tag;
            }
        }
    }

    // Sort alphabetically and take top 3
    sort($allTags);
    $allTags = array_slice($allTags, 0, 3);

    api_log('INFO', $endpoint, 'tags_search', 'Tag search completed', [
        'q' => $q,
        'total' => count($allTags),
    ]);

    echo json_encode(['tags' => $allTags]);
} catch (PDOException $e) {
    api_log_db_error($endpoint, 'tags_search', $e, ['q' => $q]);
    echo json_encode(['tags' => []]);
}
