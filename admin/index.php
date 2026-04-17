<?php
// admin/index.php - Главная панель администратора (ДАШБОРД)

session_start();
require_once '../config.php';

// Проверка авторизации
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();

// Получение общей статистики
$stats = [];

// Всего ссылок
$stmt = $pdo->query("SELECT COUNT(*) as total FROM urls");
$stats['total_urls'] = $stmt->fetch()['total'];

// Всего переходов
$stmt = $pdo->query("SELECT COUNT(*) as total FROM url_stats");
$stats['total_clicks'] = $stmt->fetch()['total'];

// Активных ссылок
$stmt = $pdo->query("SELECT COUNT(*) as total FROM urls WHERE is_active = 1");
$stats['active_urls'] = $stmt->fetch()['total'];

// Переходов за сегодня
$stmt = $pdo->query("SELECT COUNT(*) as total FROM url_stats WHERE DATE(accessed_at) = CURDATE()");
$stats['today_clicks'] = $stmt->fetch()['total'];

// Переходов за вчера
$stmt = $pdo->query("SELECT COUNT(*) as total FROM url_stats WHERE DATE(accessed_at) = CURDATE() - INTERVAL 1 DAY");
$stats['yesterday_clicks'] = $stmt->fetch()['total'];

// Переходов за неделю
$stmt = $pdo->query("SELECT COUNT(*) as total FROM url_stats WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['week_clicks'] = $stmt->fetch()['total'];

// Переходов за месяц
$stmt = $pdo->query("SELECT COUNT(*) as total FROM url_stats WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['month_clicks'] = $stmt->fetch()['total'];

// Топ ссылок по переходам
$stmt = $pdo->query("SELECT u.short_code, u.original_url, COUNT(s.id) as clicks 
                     FROM urls u 
                     LEFT JOIN url_stats s ON u.id = s.url_id 
                     GROUP BY u.id 
                     ORDER BY clicks DESC 
                     LIMIT 10");
$topUrls = $stmt->fetchAll();

// Статистика по странам (если данные есть)
$stmt = $pdo->query("SELECT country, COUNT(*) as clicks FROM url_stats WHERE country != '' GROUP BY country ORDER BY clicks DESC LIMIT 10");
$countries = $stmt->fetchAll();

// Статистика по браузерам
$stmt = $pdo->query("SELECT browser, COUNT(*) as clicks FROM url_stats GROUP BY browser ORDER BY clicks DESC");
$browsers = $stmt->fetchAll();

// Статистика по ОС
$stmt = $pdo->query("SELECT os, COUNT(*) as clicks FROM url_stats GROUP BY os ORDER BY clicks DESC");
$osStats = $stmt->fetchAll();

// Статистика по устройствам
$stmt = $pdo->query("SELECT device_type, COUNT(*) as clicks FROM url_stats GROUP BY device_type ORDER BY clicks DESC");
$deviceStats = $stmt->fetchAll();

// Статистика по дням (последние 30 дней)
$stmt = $pdo->query("SELECT DATE(accessed_at) as date, COUNT(*) as clicks 
                     FROM url_stats 
                     WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                     GROUP BY DATE(accessed_at) 
                     ORDER BY date ASC");
$dailyStats = $stmt->fetchAll();

// Последние ссылки
$stmt = $pdo->query("SELECT * FROM urls ORDER BY created_at DESC LIMIT 10");
$recentUrls = $stmt->fetchAll();

// Подготовка данных для графиков
$chartLabels = array_column($dailyStats, 'date');
$chartData = array_column($dailyStats, 'clicks');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Дашборд</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .sidebar h2 {
            margin-bottom: 30px;
            font-size: 1.5rem;
        }
        .sidebar nav a {
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #34495e;
        }
        .sidebar nav a.logout {
            background: #e74c3c;
            margin-top: 20px;
        }
        .main-content {
            padding: 30px;
            background: #f5f6fa;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card.primary { border-left: 4px solid #3498db; }
        .stat-card.success { border-left: 4px solid #2ecc71; }
        .stat-card.warning { border-left: 4px solid #f39c12; }
        .stat-card.danger { border-left: 4px solid #e74c3c; }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .data-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .data-table h3 {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .truncate {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            .sidebar {
                padding: 15px;
            }
            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .sidebar nav a {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body style="display: block; background: #f5f6fa;">
    <div class="admin-layout">
        <aside class="sidebar">
            <h2>🎛️ Админка</h2>
            <nav>
                <a href="index.php" class="active">📊 Дашборд</a>
                <a href="urls.php">🔗 Все ссылки</a>
                <a href="analytics.php">📈 Аналитика</a>
                <a href="settings.php">⚙️ Настройки</a>
                <a href="logs.php">📝 Логи</a>
                <a href="logout.php" class="logout">🚪 Выход</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>📊 Панель управления</h1>
                <p>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
            </div>

            <!-- Карточки статистики -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <h3>Всего ссылок</h3>
                    <div class="value"><?php echo number_format($stats['total_urls']); ?></div>
                </div>
                <div class="stat-card success">
                    <h3>Активных ссылок</h3>
                    <div class="value"><?php echo number_format($stats['active_urls']); ?></div>
                </div>
                <div class="stat-card warning">
                    <h3>Всего переходов</h3>
                    <div class="value"><?php echo number_format($stats['total_clicks']); ?></div>
                </div>
                <div class="stat-card danger">
                    <h3>Переходов сегодня</h3>
                    <div class="value"><?php echo number_format($stats['today_clicks']); ?></div>
                </div>
            </div>

            <!-- Дополнительная статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Переходов вчера</h3>
                    <div class="value"><?php echo number_format($stats['yesterday_clicks']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Переходов за неделю</h3>
                    <div class="value"><?php echo number_format($stats['week_clicks']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Переходов за месяц</h3>
                    <div class="value"><?php echo number_format($stats['month_clicks']); ?></div>
                </div>
            </div>

            <!-- Графики -->
            <div class="charts-grid">
                <div class="chart-container">
                    <h3>📈 Переходы за последние 30 дней</h3>
                    <canvas id="dailyChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>🌐 Устройства</h3>
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-container">
                    <h3>🖥️ Браузеры</h3>
                    <canvas id="browserChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>💻 Операционные системы</h3>
                    <canvas id="osChart"></canvas>
                </div>
            </div>

            <!-- Топ ссылок -->
            <div class="data-table">
                <h3>🏆 Топ ссылок по переходам</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Короткий код</th>
                            <th>Оригинальная ссылка</th>
                            <th>Переходов</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topUrls as $url): ?>
                        <tr>
                            <td><a href="../<?php echo htmlspecialchars($url['short_code']); ?>" target="_blank"><?php echo htmlspecialchars($url['short_code']); ?></a></td>
                            <td class="truncate"><?php echo htmlspecialchars($url['original_url']); ?></td>
                            <td><?php echo number_format($url['clicks']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Последние ссылки -->
            <div class="data-table">
                <h3>🕐 Последние добавленные ссылки</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Код</th>
                            <th>Оригинал</th>
                            <th>Дата создания</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUrls as $url): ?>
                        <tr>
                            <td><?php echo $url['id']; ?></td>
                            <td><a href="../<?php echo htmlspecialchars($url['short_code']); ?>" target="_blank"><?php echo htmlspecialchars($url['short_code']); ?></a></td>
                            <td class="truncate"><?php echo htmlspecialchars($url['original_url']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($url['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // График переходов по дням
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Переходы',
                    data: <?php echo json_encode($chartData); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // График устройств
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($deviceStats, 'device_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($deviceStats, 'clicks')); ?>,
                    backgroundColor: ['#3498db', '#2ecc71', '#e74c3c']
                }]
            },
            options: {
                responsive: true
            }
        });

        // График браузеров
        const browserCtx = document.getElementById('browserChart').getContext('2d');
        new Chart(browserCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($browsers, 'browser')); ?>,
                datasets: [{
                    label: 'Переходы',
                    data: <?php echo json_encode(array_column($browsers, 'clicks')); ?>,
                    backgroundColor: '#9b59b6'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // График ОС
        const osCtx = document.getElementById('osChart').getContext('2d');
        new Chart(osCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($osStats, 'os')); ?>,
                datasets: [{
                    label: 'Переходы',
                    data: <?php echo json_encode(array_column($osStats, 'clicks')); ?>,
                    backgroundColor: '#e67e22'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>
