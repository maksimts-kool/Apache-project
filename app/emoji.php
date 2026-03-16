<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: /");
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.*, u.username
    FROM emojis e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$_GET['id']]);
$emoji = $stmt->fetch();

if (!$emoji) {
    header("Location: /");
    exit;
}

$author = $emoji['is_anonymous'] ? 'Anonymous' : '@' . ($emoji['username'] ?? 'unknown');
$tags = $emoji['tags'] ? explode(',', $emoji['tags']) : [];
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $emoji['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($emoji['name']) ?> — KawaiiEmoji</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="/" class="logo">
                <span class="logo-icon">🌸</span>
                <span>KawaiiEmoji</span>
            </a>
            <div class="header-actions">
                <a href="/" class="btn btn-ghost btn-sm">Back to Gallery</a>
            </div>
        </div>
    </header>

    <div class="toast-container" id="toast-container"></div>

    <main class="emoji-detail">
        <div class="emoji-detail-card">
            <?php if ($is_owner): ?>
            <div style="text-align: right; margin-bottom: -30px;">
                <a href="/upload.php?id=<?= $emoji['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
            </div>
            <?php endif; ?>

            <div class="emoji-large"><?= htmlspecialchars($emoji['symbol']) ?></div>

            <h1><?= htmlspecialchars($emoji['name']) ?></h1>

            <div class="detail-meta">
                <span>By <?= htmlspecialchars($author) ?></span>
                <span>•</span>
                <span><?= htmlspecialchars(ucfirst($emoji['category'])) ?></span>
                <span>•</span>
                <span>💾 <?= number_format($emoji['downloads']) ?></span>
                <span>•</span>
                <span>📅 <?= date('M j, Y', strtotime($emoji['created_at'])) ?></span>
            </div>

            <?php if (!empty($tags)): ?>
            <div class="detail-tags">
                <?php foreach ($tags as $tag): ?>
                    <span class="tag">#<?= htmlspecialchars(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($emoji['description'])): ?>
            <div class="detail-description">
                <?= nl2br(htmlspecialchars($emoji['description'])) ?>
            </div>
            <?php endif; ?>

            <div class="detail-actions">
                <button class="btn btn-primary btn-copy" style="padding: 12px 32px; font-size: 16px;" data-symbol="<?= htmlspecialchars($emoji['symbol'], ENT_QUOTES) ?>">
                    📋 Copy Emoji
                </button>
                <button class="btn btn-secondary btn-like" style="padding: 12px 24px; font-size: 16px;" data-id="<?= $emoji['id'] ?>">
                    ♡ <span class="like-count"><?= number_format($emoji['likes']) ?></span>
                </button>
            </div>
        </div>
    </main>

    <script src="/assets/js/app.js"></script>
</body>
</html>
