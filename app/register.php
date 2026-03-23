<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — KawaiiEmoji</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">

    <div class="toast-container" id="toast-container"></div>

    <div class="auth-card">
        <a href="index.php" class="back-arrow" style="position: absolute; top: 16px; left: 16px;">←</a>
        <div class="auth-logo">✨</div>
        <h1>Create Account</h1>
        <p class="auth-subtitle">Join us and share your creations!</p>

        <form id="register-form">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <span class="input-icon">@</span>
                    <input type="text" id="username" name="username" placeholder="kawaii_star" required pattern="[A-Za-z0-9_]{3,20}" title="3-20 characters, letters, numbers and underscores only">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">📧</span>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
                    <button type="button" class="password-toggle">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="register-btn">Register ✨</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="/login.php">Log in</a>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
    <script>
        document.getElementById('register-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('register-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Creating...';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('action', 'register');

            fetch('/api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Account created! Please log in.', 'success');
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 1500);
                } else {
                    showToast(data.message || 'Registration failed', 'error');
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
    </script>
</body>
</html>
