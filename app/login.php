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
    <title>Log in — KawaiiEmoji</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">

    <div class="toast-container" id="toast-container"></div>

    <div class="auth-card">
        <a href="index.php" class="back-arrow" style="position: absolute; top: 16px; left: 16px;">←</a>
        <div class="auth-logo">🌸</div>
        <h1>Welcome Back!</h1>
        <p class="auth-subtitle">Log in to create and share kawaii ✨</p>

        <form id="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="login-btn">Log in 🌸</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="/register.php">Register</a>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('login-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Logging in...';
            btn.disabled = true;

            const formData = new FormData(this);
            formData.append('action', 'login');

            fetch('/api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/';
                } else {
                    showToast(data.message || 'Login failed', 'error');
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
