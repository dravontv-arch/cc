<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Shortener</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .stub-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        .logo {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .admin-link {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 20px;
        }
        .admin-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .features {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
        .feature {
            display: inline-block;
            margin: 10px 15px;
            color: #888;
            font-size: 14px;
        }
        @media (max-width: 480px) {
            .stub-container {
                padding: 40px 25px;
            }
            .logo {
                font-size: 60px;
            }
            h1 {
                font-size: 22px;
            }
            p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="stub-container">
        <div class="logo">🔗</div>
        <h1>URL Shortener</h1>
        <p>Сервис сокращения ссылок.<br>Все операции выполняются через панель администратора.</p>
        <a href="admin/login.php" class="admin-link">Войти в админ-панель</a>
        
        <div class="features">
            <div class="feature">⚡ Быстрое сокращение</div>
            <div class="feature">📊 Подробная аналитика</div>
            <div class="feature">🔒 Безопасно</div>
        </div>
    </div>
</body>
</html>
