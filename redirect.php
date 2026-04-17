<?php
// redirect.php - Перенаправление по короткой ссылке

require_once 'config.php';

$shortCode = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($shortCode)) {
    header('HTTP/1.0 404 Not Found');
    echo 'Ссылка не найдена';
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Получение оригинальной ссылки
    $stmt = $pdo->prepare("SELECT id, original_url FROM urls WHERE short_code = ? AND is_active = 1");
    $stmt->execute([$shortCode]);
    $url = $stmt->fetch();
    
    if (!$url) {
        header('HTTP/1.0 404 Not Found');
        echo 'Ссылка не найдена или деактивирована';
        exit;
    }
    
    // Сбор информации о пользователе для статистики
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Определение браузера
    $browser = 'Unknown';
    if (preg_match('/Chrome\//', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\//', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/Safari\//', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/Edge\//', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/MSIE|Trident\//', $userAgent)) $browser = 'IE';
    elseif (preg_match('/Opera|OPR\//', $userAgent)) $browser = 'Opera';
    
    // Определение ОС
    $os = 'Unknown';
    if (preg_match('/Windows NT 10/', $userAgent)) $os = 'Windows 10';
    elseif (preg_match('/Windows NT 6.3/', $userAgent)) $os = 'Windows 8.1';
    elseif (preg_match('/Windows NT 6.2/', $userAgent)) $os = 'Windows 8';
    elseif (preg_match('/Windows NT 6.1/', $userAgent)) $os = 'Windows 7';
    elseif (preg_match('/Mac OS X/', $userAgent)) $os = 'macOS';
    elseif (preg_match('/Linux/', $userAgent)) $os = 'Linux';
    elseif (preg_match('/Android/', $userAgent)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/', $userAgent)) $os = 'iOS';
    
    // Определение типа устройства
    $deviceType = 'desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod/', $userAgent)) {
        $deviceType = 'mobile';
    }
    
    // Геолокация (упрощенная, можно добавить через API)
    $country = '';
    $city = '';
    
    // Сохранение статистики
    $stmt = $pdo->prepare("INSERT INTO url_stats (url_id, ip_address, user_agent, referer, country, city, browser, os, device_type) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $url['id'],
        $ipAddress,
        $userAgent,
        $referer,
        $country,
        $city,
        $browser,
        $os,
        $deviceType
    ]);
    
    // Перенаправление
    header('Location: ' . $url['original_url'], true, 301);
    exit;
    
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Ошибка сервера';
}
?>
