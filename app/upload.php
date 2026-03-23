<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';
require_once 'categories.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$is_edit = false;
$emoji = null;

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM emojis WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $emoji = $stmt->fetch();

    if (!$emoji || $emoji['user_id'] != $_SESSION['user_id']) {
        header("Location: /");
        exit;
    }
    $is_edit = true;
}

$categories = getAllCategories($pdo);
$category_emojis = getAvailableCategoryEmojis();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Upload' ?> Emoji — KawaiiEmoji</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body class="upload-page">
    <header class="header">
        <div class="header-inner">
            <a href="/" class="logo">
                <span class="logo-icon">🌸</span>
                <span>KawaiiEmoji</span>
            </a>
            <div class="header-actions">
                <a href="/profile.php?id=<?= $_SESSION['user_id'] ?>" class="btn btn-ghost btn-sm">👤 Profile</a>
                <a href="/" class="btn btn-ghost btn-sm">Back to Gallery</a>
            </div>
        </div>
    </header>

    <div class="toast-container" id="toast-container"></div>

    <main class="upload-container">
        <h1><?= $is_edit ? 'Edit Emoji ✨' : 'Upload Emoji 🌸' ?></h1>

        <div class="upload-layout">
            <!-- Left: Preview Zone -->
            <div class="preview-zone <?= $is_edit ? 'has-content' : '' ?>">
                <?php if ($is_edit): ?>
                <div class="live-preview"><?= htmlspecialchars($emoji['symbol']) ?></div>
                <div class="live-preview-label">Live Preview</div>
                <?php else: ?>
                <div class="live-preview">( ? )</div>
                <div class="live-preview-label">Live Preview</div>
                <?php endif; ?>
            </div>

            <!-- Right: Form -->
            <div class="metadata-form">
                <form id="upload-form">
                    <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= $emoji['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="emoji-symbol">Emoji Characters *</label>
                        <div class="input-wrapper no-icon">
                            <textarea id="emoji-symbol" name="symbol" required
                                placeholder="(づ｡◕‿‿◕｡)づ"><?= $is_edit ? htmlspecialchars($emoji['symbol']) : '' ?></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="name">Emoji Name *</label>
                        <div class="input-wrapper no-icon">
                            <input type="text" id="name" name="name" required placeholder="e.g. Kawaii Hug"
                                maxlength="40" data-maxlength="40"
                                value="<?= $is_edit ? htmlspecialchars($emoji['name']) : '' ?>">
                        </div>
                        <div class="char-counter">0 / 40</div>
                    </div>

                    <div class="form-group">
                        <label>Tags (max 10)</label>
                        <div class="tags-container">
                            <input type="text" class="tags-input" placeholder="Add tag and press Enter...">
                        </div>
                        <div class="tags-hint">Press Enter to add • click ✕ to remove</div>
                        <input type="hidden" id="tags-hidden" name="tags"
                            value="<?= $is_edit ? htmlspecialchars($emoji['tags']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <div class="category-select-row">
                            <div class="input-wrapper no-icon category-select-wrapper">
                                <select id="category" name="category" required>
                                    <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select category
                                    </option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['slug'], ENT_QUOTES) ?>"
                                        <?= ($is_edit && $emoji['category'] === $category['slug']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(buildCategoryLabel($category)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-secondary category-add-btn" id="open-category-modal">＋
                                New</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <div class="input-wrapper no-icon">
                            <textarea id="description" name="description" placeholder="A short description..."
                                maxlength="200"
                                data-maxlength="200"><?= $is_edit ? htmlspecialchars($emoji['description']) : '' ?></textarea>
                        </div>
                        <div class="char-counter">0 / 200</div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                            <?= ($is_edit && $emoji['is_anonymous']) ? 'checked disabled' : '' ?>>
                        <div>
                            <label for="is_anonymous">Publish anonymously</label>
                            <span class="checkbox-hint">Your username will not be shown.
                                <?= $is_edit ? '(Cannot be changed after publication)' : '' ?></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block"
                        id="submit-btn"><?= $is_edit ? 'Save Changes ✓' : 'Publish Emoji 🌸' ?></button>

                    <?php if ($is_edit): ?>
                    <button type="button" class="btn btn-danger btn-block" style="margin-top: 12px;"
                        id="delete-btn">Delete Emoji 🗑️</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="category-modal" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
            <button type="button" class="modal-close" data-close-category-modal aria-label="Close">×</button>
            <h2 id="category-modal-title">Create category</h2>
            <p class="modal-subtitle">Choose an emoji and enter the category name.</p>

            <form id="category-create-form">
                <div class="form-group">
                    <label for="category-emoji">Emoji *</label>
                    <input type="hidden" id="category-emoji" name="emoji" required>
                    <div class="emoji-picker" id="category-emoji-picker" role="radiogroup" aria-label="Choose emoji">
                        <?php foreach ($category_emojis as $emoji_option): ?>
                        <button type="button" class="emoji-picker-option" data-category-emoji="<?= htmlspecialchars($emoji_option, ENT_QUOTES) ?>"
                            aria-pressed="false">
                            <?= htmlspecialchars($emoji_option) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category-name">Category name *</label>
                    <div class="input-wrapper no-icon">
                        <input type="text" id="category-name" name="name" required placeholder="My category"
                            maxlength="30" autocomplete="off">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" data-close-category-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="create-category-btn">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
    <script>
    document.getElementById('upload-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Saving...';
        btn.disabled = true;

        const isEdit = <?= $is_edit ? 'true' : 'false' ?>;
        const method = isEdit ? 'PUT' : 'POST';

        // Convert form data to JSON for PUT request, or use FormData for POST
        let body;
        const formData = new FormData(this);

        if (isEdit) {
            const data = {};
            formData.forEach((value, key) => data[key] = value);
            // Ensure checkbox value is passed if it's checked (disabled checkboxes aren't included in FormData)
            if (document.getElementById('is_anonymous').checked) {
                data['is_anonymous'] = 1;
            } else {
                data['is_anonymous'] = 0;
            }
            body = JSON.stringify(data);
        } else {
            body = formData;
        }

        fetch('/api/emojis.php', {
                method: method,
                headers: isEdit ? {
                    'Content-Type': 'application/json'
                } : {},
                body: body
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(isEdit ? 'Changes saved!' : 'Emoji published!', 'success');
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1500);
                } else {
                    showToast(data.message || 'Error occurred', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(() => {
                showToast('An error occurred. Please try again.', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    });

    <?php if ($is_edit): ?>
    document.getElementById('delete-btn').addEventListener('click', function() {
        if (confirm('Are you sure you want to delete this emoji? This cannot be undone.')) {
            fetch('/api/emojis.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: <?= $emoji['id'] ?>
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Emoji deleted.');
                        window.location.href = '/';
                    } else {
                        showToast(data.message || 'Error deleting', 'error');
                    }
                });
        }
    });
    <?php endif; ?>
    </script>
</body>

</html>
