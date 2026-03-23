<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no id, redirect to own profile or login
if ($profile_id <= 0) {
    if (isset($_SESSION['user_id'])) {
        header("Location: /profile.php?id=" . $_SESSION['user_id']);
    } else {
        header("Location: /login.php");
    }
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: /");
    exit;
}

$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_id;

// Count user's emojis
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM emojis WHERE user_id = ?");
$countStmt->execute([$profile_id]);
$emoji_count = $countStmt->fetch()['total'] ?? 0;

// Count favorites (only for owner)
$fav_count = 0;
if ($is_owner) {
    $favStmt = $pdo->prepare("SELECT COUNT(*) as total FROM emoji_likes WHERE user_id = ?");
    $favStmt->execute([$profile_id]);
    $fav_count = $favStmt->fetch()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> — KawaiiEmoji</title>
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

    <main class="profile-page">
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(mb_substr($user['username'], 0, 1)) ?>
            </div>
            <h1>@<?= htmlspecialchars($user['username']) ?></h1>
            <p class="profile-joined">Joined <?= date('M Y', strtotime($user['created_at'])) ?></p>
        </div>

        <div class="profile-tabs">
            <button class="profile-tab active" data-tab="emojis">
                🎨 My Emojis <span class="tab-count"><?= $emoji_count ?></span>
            </button>
            <?php if ($is_owner): ?>
            <button class="profile-tab" data-tab="favorites">
                ♥ Favorites <span class="tab-count"><?= $fav_count ?></span>
            </button>
            <?php endif; ?>
        </div>

        <div class="gallery-grid profile-grid" id="profile-grid">
            <!-- Loaded via JS -->
        </div>

        <p class="profile-empty" id="profile-empty" style="display:none;">No emojis here yet</p>
    </main>

    <button class="back-to-top">↑</button>

    <script src="/assets/js/app.js"></script>
    <script>
        const profileId = <?= $profile_id ?>;
        const isOwner = <?= $is_owner ? 'true' : 'false' ?>;
        let currentTab = 'emojis';

        let profileLoadingTimeout = null;

        function loadProfileEmojis(tab) {
            currentTab = tab;
            const grid = document.getElementById('profile-grid');
            const empty = document.getElementById('profile-empty');
            
            if (profileLoadingTimeout) clearTimeout(profileLoadingTimeout);
            empty.style.display = 'none';

            profileLoadingTimeout = setTimeout(() => {
                grid.innerHTML = '';
                for (let i = 0; i < 6; i++) {
                    grid.innerHTML += `
                        <div class="skeleton-card">
                            <div class="skeleton-row row-1"><div class="skeleton-preview"></div></div>
                            <div class="skeleton-row row-2"><div class="skeleton-line"></div><div class="skeleton-line short"></div></div>
                            <div class="skeleton-row row-3"><div class="skeleton-line"></div></div>
                            <div class="skeleton-row row-4"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>
                        </div>
                    `;
                }
            }, 1000);

            fetch(`/api/profile.php?id=${profileId}&tab=${tab}`)
                .then(res => res.json())
                .then(data => {
                    if (profileLoadingTimeout) {
                        clearTimeout(profileLoadingTimeout);
                        profileLoadingTimeout = null;
                    }
                    grid.innerHTML = '';
                    if (data.emojis && data.emojis.length > 0) {
                        data.emojis.forEach(emoji => {
                            grid.innerHTML += renderEmojiCard(emoji);
                        });
                        initCopyButtons();
                        initLikeButtons();
                        revealCards(grid.querySelectorAll('.emoji-card'));
                    } else {
                        empty.style.display = 'block';
                    }
                })
                .catch(() => {
                    if (profileLoadingTimeout) {
                        clearTimeout(profileLoadingTimeout);
                        profileLoadingTimeout = null;
                    }
                    grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#F87171;padding:40px;">Failed to load</p>';
                });
        }

        document.querySelectorAll('.profile-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                loadProfileEmojis(tab.dataset.tab);
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadProfileEmojis('emojis');
        });
    </script>
</body>
</html>
