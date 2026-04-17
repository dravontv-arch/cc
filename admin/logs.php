<?php
// admin/logs.php - Логи действий администратора

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Получение логов
$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS l.*, u.username 
                       FROM admin_logs l 
                       LEFT JOIN users u ON l.user_id = u.id 
                       ORDER BY l.created_at DESC 
                       LIMIT $perPage OFFSET $offset");
$stmt->execute();
$logs = $stmt->fetchAll();

// Всего записей
$stmt = $pdo->query("SELECT FOUND_ROWS() as total");
$totalLogs = $stmt->fetch()['total'];
$totalPages = ceil($totalLogs / $perPage);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи - Админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
        .sidebar { background: #2c3e50; color: white; padding: 20px; }
        .sidebar h2 { margin-bottom: 30px; font-size: 1.5rem; }
        .sidebar nav a { display: block; color: #ecf0f1; text-decoration: none; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; }
        .sidebar nav a:hover, .sidebar nav a.active { background: #34495e; }
        .sidebar nav a.logout { background: #e74c3c; margin-top: 20px; }
        .main-content { padding: 30px; background: #f5f6fa; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 15px; overflow: hidden; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #f8f9fa; font-weight: 600; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a { padding: 10px 15px; background: white; border-radius: 8px; text-decoration: none; color: #333; }
        .pagination a.active { background: #3498db; color: white; }
        .action-login { color: #27ae60; }
        .action-delete { color: #e74c3c; }
        .action-update { color: #f39c12; }
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
                <a href="settings.php">⚙️ Настройки</a>
                <a href="logs.php" class="active">📝 Логи</a>
                <a href="logout.php" class="logout">🚪 Выход</a>
            </nav>
        </aside>

        <main class="main-content">
            <h1>📝 Журнал действий администратора</h1>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Описание</th>
                        <th>IP адрес</th>
                        <th>Время</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                        <td class="action-<?php echo strtolower(explode('_', $log['action'])[0]); ?>">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        <td><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
