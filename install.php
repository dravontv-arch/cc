<?php
// install.php - Установка базы данных

require_once 'config.php';

try {
    // Подключение без выбора БД для создания
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Создание базы данных
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // Таблица сокращенных ссылок
    $pdo->exec("CREATE TABLE IF NOT EXISTS urls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_url TEXT NOT NULL,
        short_code VARCHAR(50) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_short_code (short_code),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Таблица статистики переходов
    $pdo->exec("CREATE TABLE IF NOT EXISTS url_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        referer TEXT,
        country VARCHAR(2),
        city VARCHAR(100),
        browser VARCHAR(50),
        os VARCHAR(50),
        device_type VARCHAR(20),
        accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE,
        INDEX idx_url_id (url_id),
        INDEX idx_accessed_at (accessed_at),
        INDEX idx_country (country)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Таблица настроек
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Таблица пользователей (администраторов)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Таблица логов действий администратора
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Добавление настроек по умолчанию
    $settings = [
        ['default_length', DEFAULT_SHORT_LENGTH],
        ['allow_custom_alias', ALLOW_CUSTOM_ALIAS ? '1' : '0'],
        ['site_title', 'URL Shortener'],
        ['site_description', 'Сервис сокращения ссылок']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute($setting);
    }
    
    // Создание администратора по умолчанию
    $adminPassword = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
    $stmt->execute([ADMIN_USERNAME, $adminPassword, 'admin@example.com']);
    
    echo "<h1>Установка завершена успешно!</h1>";
    echo "<p>База данных '" . DB_NAME . "' создана.</p>";
    echo "<p>Таблицы созданы:</p>";
    echo "<ul>";
    echo "<li>urls - сокращенные ссылки</li>";
    echo "<li>url_stats - статистика переходов</li>";
    echo "<li>settings - настройки</li>";
    echo "<li>users - пользователи</li>";
    echo "<li>admin_logs - логи администратора</li>";
    echo "</ul>";
    echo "<p><strong>Логин администратора:</strong> " . ADMIN_USERNAME . "</p>";
    echo "<p><strong>Пароль:</strong> " . ADMIN_PASSWORD . "</p>";
    echo "<p><a href='index.php'>Перейти на главную</a> | <a href='admin/login.php'>Войти в админ-панель</a></p>";
    echo "<p style='color:red;'><strong>ВАЖНО:</strong> Удалите файл install.php после установки!</p>";
    
} catch (PDOException $e) {
    echo "<h1>Ошибка установки</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
