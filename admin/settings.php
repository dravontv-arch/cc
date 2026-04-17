<?php
// admin/settings.php - Настройки системы

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();
$message = '';
$messageType = '';

// Сохранение настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'default_length' => isset($_POST['default_length']) ? (int)$_POST['default_length'] : 6,
        'allow_custom_alias' => isset($_POST['allow_custom_alias']) ? '1' : '0',
        'site_title' => isset($_POST['site_title']) ? trim($_POST['site_title']) : 'URL Shortener',
        'site_description' => isset($_POST['site_description']) ? trim($_POST['site_description']) : 'Сервис сокращения ссылок'
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }

    // Смена пароля
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $passwordHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $_SESSION['admin_id']]);
            $message = 'Пароль успешно изменен';
            $messageType = 'success';
        } else {
            $message = 'Пароли не совпадают';
            $messageType = 'error';
        }
    } else {
        $message = 'Настройки сохранены';
        $messageType = 'success';
    }

    // Логирование
    $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['admin_id'], 'update_settings', 'Обновление настроек системы', $_SERVER['REMOTE_ADDR']]);
}

// Получение текущих настроек
$stmt = $pdo->query("SELECT * FROM settings");
$currentSettings = [];
while ($row = $stmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
        .sidebar { background: #2c3e50; color: white; padding: 20px; }
        .sidebar h2 { margin-bottom: 30px; font-size: 1.5rem; }
        .sidebar nav a { display: block; color: #ecf0f1; text-decoration: none; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; }
        .sidebar nav a:hover, .sidebar nav a.active { background: #34495e; }
        .sidebar nav a.logout { background: #e74c3c; margin-top: 20px; }
        .main-content { padding: 30px; background: #f5f6fa; }
        .settings-card { background: white; padding: 30px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="password"], .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .form-group input[type="checkbox"] { width: 20px; height: 20px; }
        .form-group small { display: block; margin-top: 5px; color: #999; }
        .btn-save { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        @media (max-width: 768px) { .admin-layout { grid-template-columns: 1fr; } .sidebar nav { display: flex; flex-wrap: wrap; } }
    </style>
</head>
<body style="display: block; background: #f5f6fa;">
    <div class="admin-layout">
        <aside class="sidebar">
            <h2>🎛️ Админка</h2>
            <nav>
                <a href="index.php">📊 Дашборд</a>
                <a href="urls.php">🔗 Все ссылки</a>
                <a href="analytics.php">📈 Аналитика</a>
                <a href="settings.php" class="active">⚙️ Настройки</a>
                <a href="logs.php">📝 Логи</a>
                <a href="logout.php" class="logout">🚪 Выход</a>
            </nav>
        </aside>

        <main class="main-content">
            <h1>⚙️ Настройки системы</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" class="settings-card">
                <h2>Основные настройки</h2>
                
                <div class="form-group">
                    <label for="site_title">Название сайта:</label>
                    <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($currentSettings['site_title'] ?? 'URL Shortener'); ?>">
                </div>

                <div class="form-group">
                    <label for="site_description">Описание сайта:</label>
                    <textarea id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($currentSettings['site_description'] ?? 'Сервис сокращения ссылок'); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="default_length">Длина короткой ссылки по умолчанию:</label>
                    <input type="number" id="default_length" name="default_length" min="4" max="20" value="<?php echo htmlspecialchars($currentSettings['default_length'] ?? 6); ?>">
                    <small>От 4 до 20 символов</small>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="allow_custom_alias" name="allow_custom_alias" value="1" <?php echo ($currentSettings['allow_custom_alias'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <label for="allow_custom_alias">Разрешить пользовательские алиасы</label>
                    </div>
                </div>

                <button type="submit" class="btn-save">Сохранить настройки</button>
            </form>

            <form method="POST" class="settings-card">
                <h2>Безопасность</h2>
                
                <div class="form-group">
                    <label for="new_password">Новый пароль:</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Оставьте пустым чтобы не менять">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Подтверждение пароля:</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Подтвердите новый пароль">
                </div>

                <button type="submit" class="btn-save">Сменить пароль</button>
            </form>

            <div class="settings-card">
                <h2>Информация о сервере</h2>
                <table style="width: 100%;">
                    <tr><td style="padding: 10px 0;"><strong>PHP Version:</strong></td><td><?php echo phpversion(); ?></td></tr>
                    <tr><td style="padding: 10px 0;"><strong>MySQL Version:</strong></td><td><?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></td></tr>
                    <tr><td style="padding: 10px 0;"><strong>Server Software:</strong></td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td></tr>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
