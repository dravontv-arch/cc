<?php
// admin/logout.php - Выход из админ-панели

session_start();
require_once '../config.php';

// Логирование выхода
if (isset($_SESSION['admin_id'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], 'logout', 'Выход из системы', $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Игнорируем ошибки логирования при выходе
    }
}

// Уничтожение сессии
session_destroy();

// Перенаправление на страницу входа
header('Location: login.php');
exit;
?>
