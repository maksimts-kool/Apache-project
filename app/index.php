<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';

// Fetch the total number of emojis for the live stat
$stmt = $pdo->query("SELECT COUNT(*) as total FROM emojis");
$row = $stmt->fetch();
$total_emojis = $row['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KawaiiEmoji — Create & Share Text Emojis</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="/" class="logo">
                <span class="logo-icon">🌸</span>
                <span>KawaiiEmoji</span>
            </a>
            <div class="search-bar">
                <input type="text" id="search-input" placeholder="Find your kawaii...">
                <span class="search-icon">🔍</span>
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

    <nav class="category-nav">
        <div class="category-nav-inner">
            <button class="category-tab active" data-category="">✨ All</button>
            <button class="category-tab" data-category="kawaii">🎌 Kawaii</button>
            <button class="category-tab" data-category="funny">😂 Funny</button>
            <button class="category-tab" data-category="sad">😭 Sad</button>
            <button class="category-tab" data-category="love">💖 Love</button>
        </div>
    </nav>

    <?php if (!isset($_SESSION['user_id'])): ?>
    <section class="hero">
        <div class="floating-emojis">
            <div class="floating-emoji" style="left: 10%; top: 20%;">(づ｡◕‿‿◕｡)づ</div>
            <div class="floating-emoji" style="left: 80%; top: 30%;">(◕‿◕✿)</div>
            <div class="floating-emoji" style="left: 20%; top: 70%;">(=^･ω･^=)</div>
            <div class="floating-emoji" style="left: 70%; top: 60%;">ʕ•ᴥ•ʔ</div>
        </div>
        <div class="hero-content">
            <h1>Create. Share. Kawaii.</h1>
            <p>Welcome to KawaiiEmoji, the cutest collection of text-based kaomoji and unicode smiles. Join our community to share your own creations!</p>
            <div class="hero-buttons">
                <a href="/register.php" class="btn btn-primary">Start for free</a>
                <a href="#gallery" class="btn btn-secondary">Browse Emojis</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <main id="gallery">
        <div class="gallery-header">
            <div class="gallery-count">Showing: <?= number_format($total_emojis) ?> emojis</div>
            <div class="gallery-sort">
                <select id="sort-select">
                    <option value="popular">⭐ Popular</option>
                    <option value="new">🆕 Newest</option>
                    <option value="alpha">🔤 Alphabetical</option>
                </select>
            </div>
        </div>

        <div class="gallery-grid">
            <!-- Emojis will be loaded here via JS -->
            <!-- Initial skeleton loaders -->
            <?php for ($i=0; $i<10; $i++): ?>
            <div class="skeleton-card">
                <div class="skeleton-preview"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="live-stats">
            <span><?= number_format($total_emojis) ?></span> emojis and counting ✨
        </div>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-links">
                <a href="#">About</a>
                <a href="#">FAQ</a>
                <a href="#">Rules</a>
                <a href="#">Contact</a>
            </div>
            <div class="footer-credit">
                🌸 KawaiiEmoji © 2026. Made with ♥
            </div>
        </div>
    </footer>

    <button class="back-to-top">↑</button>

    <script src="/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const q = urlParams.get('q');
            if (q) {
                const searchInput = document.getElementById('search-input');
                if (searchInput) searchInput.value = q;
                loadEmojis({ q, sort: 'popular' });
            } else {
                loadEmojis({ sort: 'popular' });
            }
        });
    </script>
</body>
</html>
