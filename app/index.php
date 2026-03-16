<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KawaiiEmoji</title>
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background: #FFF0F6;
            color: #1F1235;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            background: #fff;
            padding: 48px;
            border-radius: 24px;
            box-shadow: 0 8px 40px rgba(167,139,250,0.18);
        }
        h1 { color: #FF6B9D; }
        .emoji { font-size: 48px; margin-bottom: 16px; }
        .status { color: #34D399; font-weight: bold; }
        .info { color: #9CA3AF; font-size: 14px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">(づ｡◕‿‿◕｡)づ</div>
        <h1>KawaiiEmoji</h1>
        <p>Создавай. Делись. Кавай.</p>
        <p class="status">Webserver is running!</p>
        <p class="info">
            PHP <?php echo phpversion(); ?> |
            Apache <?php echo apache_get_version(); ?> |
            Server time: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
