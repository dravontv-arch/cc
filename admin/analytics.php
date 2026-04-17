<?php
// admin/analytics.php - Подробная аналитика

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();

// Фильтры
$dateRange = isset($_GET['range']) ? $_GET['range'] : '7days';
$urlFilter = isset($_GET['url_id']) ? (int)$_GET['url_id'] : null;

// Определение диапазона дат
switch ($dateRange) {
    case 'today': $dateCondition = 'DATE(accessed_at) = CURDATE()'; break;
    case 'yesterday': $dateCondition = 'DATE(accessed_at) = CURDATE() - INTERVAL 1 DAY'; break;
    case '7days': $dateCondition = 'accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; break;
    case '30days': $dateCondition = 'accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; break;
    case 'all': $dateCondition = '1=1'; break;
    default: $dateCondition = 'accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$where = "WHERE $dateCondition";
if ($urlFilter) {
    $where .= " AND url_id = $urlFilter";
}

// Общая статистика за период
$stmt = $pdo->query("SELECT COUNT(*) as total FROM url_stats $where");
$totalClicks = $stmt->fetch()['total'];

// Уникальные IP
$stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) as unique_ips FROM url_stats $where");
$uniqueIps = $stmt->fetch()['unique_ips'];

// Статистика по часам (за последние 24 часа)
$stmt = $pdo->query("SELECT HOUR(accessed_at) as hour, COUNT(*) as clicks 
                     FROM url_stats 
                     WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                     GROUP BY HOUR(accessed_at) 
                     ORDER BY hour ASC");
$hourlyStats = $stmt->fetchAll();

// Статистика по дням недели
$stmt = $pdo->query("SELECT DAYNAME(accessed_at) as day, COUNT(*) as clicks 
                     FROM url_stats $where 
                     GROUP BY DAYOFWEEK(accessed_at), DAYNAME(accessed_at) 
                     ORDER BY DAYOFWEEK(accessed_at)");
$dailyStats = $stmt->fetchAll();

// Топ рефереров
$stmt = $pdo->query("SELECT referer, COUNT(*) as clicks FROM url_stats $where 
                     AND referer != '' GROUP BY referer ORDER BY clicks DESC LIMIT 10");
$referers = $stmt->fetchAll();

// Статистика по странам (если есть данные)
$stmt = $pdo->query("SELECT country, COUNT(*) as clicks FROM url_stats $where 
                     AND country != '' GROUP BY country ORDER BY clicks DESC LIMIT 15");
$countries = $stmt->fetchAll();

// Статистика по городам
$stmt = $pdo->query("SELECT city, COUNT(*) as clicks FROM url_stats $where 
                     AND city != '' GROUP BY city ORDER BY clicks DESC LIMIT 10");
$cities = $stmt->fetchAll();

// Все ссылки для фильтра
$stmt = $pdo->query("SELECT id, short_code, original_url FROM urls ORDER BY created_at DESC");
$allUrls = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - Админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
        .sidebar { background: #2c3e50; color: white; padding: 20px; }
        .sidebar h2 { margin-bottom: 30px; font-size: 1.5rem; }
        .sidebar nav a { display: block; color: #ecf0f1; text-decoration: none; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; }
        .sidebar nav a:hover, .sidebar nav a.active { background: #34495e; }
        .sidebar nav a.logout { background: #e74c3c; margin-top: 20px; }
        .main-content { padding: 30px; background: #f5f6fa; }
        .filters { background: white; padding: 20px; border-radius: 15px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filters select { padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-box .value { font-size: 36px; font-weight: bold; color: #3498db; }
        .stat-box .label { color: #7f8c8d; margin-top: 5px; }
        .charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .chart-card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .data-list { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .data-list h3 { margin-bottom: 15px; }
        .list-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ecf0f1; }
        .progress-bar { background: #ecf0f1; border-radius: 10px; height: 20px; overflow: hidden; margin-top: 5px; }
        .progress-fill { background: linear-gradient(90deg, #3498db, #2ecc71); height: 100%; border-radius: 10px; }
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
                <a href="analytics.php" class="active">📈 Аналитика</a>
                <a href="transitions.php">📋 Все переходы</a>
                <a href="settings.php">⚙️ Настройки</a>
                <a href="logs.php">📝 Логи</a>
                <a href="logout.php" class="logout">🚪 Выход</a>
            </nav>
        </aside>

        <main class="main-content">
            <h1>📈 Подробная аналитика</h1>

            <form method="GET" class="filters">
                <label>Период:
                    <select name="range" onchange="this.form.submit()">
                        <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Сегодня</option>
                        <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Вчера</option>
                        <option value="7days" <?php echo $dateRange === '7days' ? 'selected' : ''; ?>>7 дней</option>
                        <option value="30days" <?php echo $dateRange === '30days' ? 'selected' : ''; ?>>30 дней</option>
                        <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>Все время</option>
                    </select>
                </label>
                <label>Ссылка:
                    <select name="url_id" onchange="this.form.submit()">
                        <option value="">Все ссылки</option>
                        <?php foreach ($allUrls as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $urlFilter == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['short_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>

            <div class="stats-row">
                <div class="stat-box">
                    <div class="value"><?php echo number_format($totalClicks); ?></div>
                    <div class="label">Переходов за период</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo number_format($uniqueIps); ?></div>
                    <div class="label">Уникальных IP</div>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <h3>🕐 Активность по часам (24ч)</h3>
                    <canvas id="hourlyChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>📅 По дням недели</h3>
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <h3>🖥️ Браузеры</h3>
                    <canvas id="browserChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>💻 ОС</h3>
                    <canvas id="osChart"></canvas>
                </div>
            </div>

            <div class="data-list">
                <h3>🔗 Топ рефереров</h3>
                <?php if (empty($referers)): ?>
                    <p>Нет данных</p>
                <?php else: ?>
                    <?php foreach ($referers as $ref): ?>
                    <div class="list-item">
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%;"><?php echo htmlspecialchars($ref['referer']); ?></span>
                        <span><?php echo number_format($ref['clicks']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($countries)): ?>
            <div class="data-list">
                <h3>🌍 Страны</h3>
                <?php foreach ($countries as $c): ?>
                <div class="list-item">
                    <div style="flex: 1;">
                        <span><?php echo htmlspecialchars($c['country']); ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($c['clicks'] / $totalClicks) * 100; ?>%"></div>
                        </div>
                    </div>
                    <span style="margin-left: 20px;"><?php echo number_format($c['clicks']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // График по часам
        new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(fn($h) => $h['hour'] . ':00', $hourlyStats)); ?>,
                datasets: [{
                    label: 'Переходы',
                    data: <?php echo json_encode(array_column($hourlyStats, 'clicks')); ?>,
                    backgroundColor: '#3498db'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        // График по дням
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($dailyStats, 'day')); ?>,
                datasets: [{
                    label: 'Переходы',
                    data: <?php echo json_encode(array_column($dailyStats, 'clicks')); ?>,
                    borderColor: '#2ecc71',
                    fill: true,
                    backgroundColor: 'rgba(46, 204, 113, 0.2)'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        // Браузеры
        <?php
        $stmt = $pdo->query("SELECT browser, COUNT(*) as clicks FROM url_stats $where GROUP BY browser ORDER BY clicks DESC");
        $browserData = $stmt->fetchAll();
        ?>
        new Chart(document.getElementById('browserChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($browserData, 'browser')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($browserData, 'clicks')); ?>,
                    backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6']
                }]
            },
            options: { responsive: true }
        });

        // ОС
        <?php
        $stmt = $pdo->query("SELECT os, COUNT(*) as clicks FROM url_stats $where GROUP BY os ORDER BY clicks DESC");
        $osData = $stmt->fetchAll();
        ?>
        new Chart(document.getElementById('osChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($osData, 'os')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($osData, 'clicks')); ?>,
                    backgroundColor: ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6']
                }]
            },
            options: { responsive: true }
        });
    </script>
</body>
</html>
