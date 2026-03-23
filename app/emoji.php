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

$user_liked = false;
if (isset($_SESSION['user_id'])) {
    $likeCheck = $pdo->prepare("SELECT 1 FROM emoji_likes WHERE user_id = ? AND emoji_id = ?");
    $likeCheck->execute([$_SESSION['user_id'], $emoji['id']]);
    $user_liked = (bool) $likeCheck->fetch();
}
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
            <div class="header-left">
                <a href="/" class="back-arrow" aria-label="Back to gallery">←</a>
                <a href="/" class="logo">
                    <span class="logo-icon">🌸</span>
                    <span>KawaiiEmoji</span>
                </a>
            </div>
            <div class="header-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/profile.php?id=<?= $_SESSION['user_id'] ?>" class="btn btn-ghost btn-sm">👤 Profile</a>
                    <a href="/upload.php" class="btn btn-primary btn-sm">Upload ✨</a>
                    <a href="/api/auth.php?action=logout" class="btn btn-ghost btn-sm">Logout</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-ghost btn-sm">Log in</a>
                    <a href="/register.php" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="toast-container" id="toast-container"></div>

    <main class="emoji-detail">
        <div class="emoji-detail-card">
            <div class="emoji-large"><?= htmlspecialchars($emoji['symbol']) ?></div>

            <h1><?= htmlspecialchars($emoji['name']) ?></h1>

            <div class="detail-meta">
                <span>By <?= htmlspecialchars($author) ?></span>
                <span>•</span>
                <span><?= htmlspecialchars(ucfirst($emoji['category'])) ?></span>
                <span>•</span>
                <span>📋 <span class="copy-count" data-id="<?= $emoji['id'] ?>"><?= number_format($emoji['downloads']) ?></span></span>
                <span>•</span>
                <span>📅 <?= date('M j, Y', strtotime($emoji['created_at'])) ?></span>
            </div>

            <?php if (!empty($tags)): ?>
            <div class="detail-tags">
                <?php foreach ($tags as $tag): ?>
                    <a href="/?q=%23<?= urlencode(trim($tag)) ?>" class="tag">#<?= htmlspecialchars(trim($tag)) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($emoji['description'])): ?>
            <div class="detail-description">
                <?= nl2br(htmlspecialchars($emoji['description'])) ?>
            </div>
            <?php endif; ?>

            <div class="detail-actions">
                <button class="btn btn-primary btn-copy" style="padding: 12px 32px; font-size: 16px;" data-symbol="<?= htmlspecialchars($emoji['symbol'], ENT_QUOTES) ?>" data-id="<?= $emoji['id'] ?>">
                    📋 Copy Emoji
                </button>
                <button class="btn btn-secondary btn-like<?= $user_liked ? ' liked' : '' ?>" style="padding: 12px 24px; font-size: 16px;" data-id="<?= $emoji['id'] ?>">
                    <span class="like-icon"><?= $user_liked ? '♥' : '♡' ?></span> <span class="like-count"><?= number_format($emoji['likes']) ?></span>
                </button>
                <?php if ($is_owner): ?>
                <a href="/upload.php?id=<?= $emoji['id'] ?>" class="btn btn-secondary" style="padding: 12px 24px; font-size: 16px;">✏️ Edit</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="/assets/js/app.js"></script>
</body>
</html>
