<?php
// admin/transitions.php - Детальная таблица всех переходов

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();

// Обработка добавления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $statId = (int)$_POST['stat_id'];
    $comment = trim($_POST['comment']);
    if ($statId > 0) {
        $stmt = $pdo->prepare("UPDATE url_stats SET comment = ? WHERE id = ?");
        $stmt->execute([$comment, $statId]);
        header('Location: transitions.php?updated=1');
        exit;
    }
}

// Фильтры
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$urlFilter = isset($_GET['url_id']) ? (int)$_GET['url_id'] : null;
$ipFilter = isset($_GET['ip']) ? trim($_GET['ip']) : '';
$countryFilter = isset($_GET['country']) ? trim($_GET['country']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = "WHERE 1=1";
$params = [];

if ($urlFilter) {
    $where .= " AND s.url_id = ?";
    $params[] = $urlFilter;
}
if ($ipFilter) {
    $where .= " AND s.ip_address LIKE ?";
    $params[] = "%$ipFilter%";
}
if ($countryFilter) {
    $where .= " AND s.country = ?";
    $params[] = $countryFilter;
}
if ($dateFrom) {
    $where .= " AND DATE(s.accessed_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND DATE(s.accessed_at) <= ?";
    $params[] = $dateTo;
}

// Получение данных с пагинацией
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM url_stats s $where");
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);

$stmt = $pdo->prepare("
    SELECT s.id, s.url_id, s.ip_address, s.user_agent, s.referer, s.country, s.city, 
           s.browser, s.os, s.device_type, s.accessed_at, s.comment,
           u.short_code, u.original_url
    FROM url_stats s
    JOIN urls u ON s.url_id = u.id
    $where
    ORDER BY s.accessed_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$transitions = $stmt->fetchAll();

// Все ссылки для фильтра
$stmt = $pdo->query("SELECT id, short_code, original_url FROM urls ORDER BY created_at DESC");
$allUrls = $stmt->fetchAll();

// Уникальные страны для фильтра
$stmt = $pdo->query("SELECT DISTINCT country FROM url_stats WHERE country != '' ORDER BY country");
$countries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все переходы - Админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
        .sidebar { background: #2c3e50; color: white; padding: 20px; }
        .sidebar h2 { margin-bottom: 30px; font-size: 1.5rem; }
        .sidebar nav a { display: block; color: #ecf0f1; text-decoration: none; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; }
        .sidebar nav a:hover, .sidebar nav a.active { background: #34495e; }
        .sidebar nav a.logout { background: #e74c3c; margin-top: 20px; }
        .main-content { padding: 30px; background: #f5f6fa; }
        .filters { background: white; padding: 20px; border-radius: 15px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filters input, .filters select { padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
        .filters button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .table-container { background: white; border-radius: 15px; padding: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: top; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        .flag { font-size: 1.2em; }
        .comment-box { width: 100%; min-height: 60px; padding: 8px; border: 1px solid #ddd; border-radius: 6px; resize: vertical; }
        .comment-form { display: flex; gap: 10px; align-items: flex-start; }
        .comment-form button { padding: 8px 15px; background: #2ecc71; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 10px 15px; background: white; border-radius: 8px; text-decoration: none; color: #333; }
        .pagination .active { background: #3498db; color: white; }
        .ip-badge { background: #e8f4fd; padding: 4px 8px; border-radius: 4px; font-family: monospace; }
        .geo-info { color: #7f8c8d; font-size: 0.9em; }
        .short-code { font-weight: bold; color: #3498db; }
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
                <a href="transitions.php" class="active">📋 Все переходы</a>
                <a href="settings.php">⚙️ Настройки</a>
                <a href="logs.php">📝 Логи</a>
                <a href="logout.php" class="logout">🚪 Выход</a>
            </nav>
        </aside>

        <main class="main-content">
            <h1>📋 Детальная таблица всех переходов</h1>

            <?php if (isset($_GET['updated'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    ✅ Комментарий сохранён
                </div>
            <?php endif; ?>

            <form method="GET" class="filters">
                <label>Ссылка:
                    <select name="url_id">
                        <option value="">Все ссылки</option>
                        <?php foreach ($allUrls as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $urlFilter == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['short_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>IP:
                    <input type="text" name="ip" placeholder="Поиск по IP" value="<?php echo htmlspecialchars($ipFilter); ?>">
                </label>
                <label>Страна:
                    <select name="country">
                        <option value="">Все страны</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['country']); ?>" <?php echo $countryFilter === $c['country'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['country']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>С:
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </label>
                <label>По:
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </label>
                <button type="submit">Фильтр</button>
                <a href="transitions.php" style="padding: 10px 20px; background: #95a5a6; color: white; border-radius: 8px; text-decoration: none;">Сброс</a>
            </form>

            <div class="table-container">
                <p style="color: #7f8c8d; margin-bottom: 15px;">
                    Всего переходов: <strong><?php echo number_format($totalRecords); ?></strong> | 
                    Страница <?php echo $page; ?> из <?php echo $totalPages; ?>
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Дата/Время</th>
                            <th>Ссылка</th>
                            <th>IP адрес</th>
                            <th>ГЕО</th>
                            <th>Браузер / ОС</th>
                            <th>Реферер</th>
                            <th>Комментарий</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transitions)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 40px;">Нет данных</td></tr>
                        <?php else: ?>
                            <?php foreach ($transitions as $t): ?>
                                <tr>
                                    <td><?php echo $t['id']; ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($t['accessed_at'])); ?></td>
                                    <td>
                                        <span class="short-code"><?php echo htmlspecialchars($t['short_code']); ?></span><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars(substr($t['original_url'], 0, 40)); ?>...</small>
                                    </td>
                                    <td><span class="ip-badge"><?php echo htmlspecialchars($t['ip_address']); ?></span></td>
                                    <td>
                                        <?php if ($t['country']): ?>
                                            <div class="geo-info">
                                                🌍 <?php echo htmlspecialchars($t['country']); ?>
                                                <?php if ($t['city']): ?>, 🏙️ <?php echo htmlspecialchars($t['city']); ?><?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #bdc3c7;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>🌐 <?php echo htmlspecialchars($t['browser']); ?></div>
                                        <div style="font-size: 0.85em; color: #7f8c8d;">💻 <?php echo htmlspecialchars($t['os']); ?> (<?php echo $t['device_type']; ?>)</div>
                                    </td>
                                    <td>
                                        <?php if ($t['referer']): ?>
                                            <small style="color: #7f8c8d;"><?php echo htmlspecialchars(substr($t['referer'], 0, 50)); ?>...</small>
                                        <?php else: ?>
                                            <span style="color: #bdc3c7;">Прямой переход</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="comment-form">
                                            <input type="hidden" name="stat_id" value="<?php echo $t['id']; ?>">
                                            <textarea name="comment" class="comment-box" placeholder="Добавить заметку..."><?php echo htmlspecialchars($t['comment'] ?? ''); ?></textarea>
                                            <button type="submit" name="add_comment">💾</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $urlFilter ? '&url_id='.$urlFilter : ''; ?><?php echo $ipFilter ? '&ip='.urlencode($ipFilter) : ''; ?><?php echo $countryFilter ? '&country='.urlencode($countryFilter) : ''; ?><?php echo $dateFrom ? '&date_from='.$dateFrom : ''; ?><?php echo $dateTo ? '&date_to='.$dateTo : ''; ?>">← Назад</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $urlFilter ? '&url_id='.$urlFilter : ''; ?><?php echo $ipFilter ? '&ip='.urlencode($ipFilter) : ''; ?><?php echo $countryFilter ? '&country='.urlencode($countryFilter) : ''; ?><?php echo $dateFrom ? '&date_from='.$dateFrom : ''; ?><?php echo $dateTo ? '&date_to='.$dateTo : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $urlFilter ? '&url_id='.$urlFilter : ''; ?><?php echo $ipFilter ? '&ip='.urlencode($ipFilter) : ''; ?><?php echo $countryFilter ? '&country='.urlencode($countryFilter) : ''; ?><?php echo $dateFrom ? '&date_from='.$dateFrom : ''; ?><?php echo $dateTo ? '&date_to='.$dateTo : ''; ?>">Вперёд →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
