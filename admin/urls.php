<?php
// admin/urls.php - Управление ссылками

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();
$message = '';
$messageType = '';

// Получение настроек
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$defaultLength = (int)($settings['default_length'] ?? 6);
$allowCustomAlias = ($settings['allow_custom_alias'] ?? '1') === '1';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Создание новой ссылки
    if ($action === 'create') {
        $originalUrl = trim($_POST['original_url'] ?? '');
        $customAlias = trim($_POST['custom_alias'] ?? '');
        $codeLength = isset($_POST['code_length']) ? (int)$_POST['code_length'] : $defaultLength;
        
        if (!empty($originalUrl)) {
            // Валидация URL
            if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
                $message = 'Некорректный URL';
                $messageType = 'error';
            } else {
                // Генерация короткого кода
                if (!empty($customAlias) && $allowCustomAlias) {
                    $shortCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $customAlias);
                    // Проверка на уникальность
                    $stmt = $pdo->prepare("SELECT id FROM urls WHERE short_code = ?");
                    $stmt->execute([$shortCode]);
                    if ($stmt->fetch()) {
                        $message = 'Такой алиас уже существует';
                        $messageType = 'error';
                        $shortCode = '';
                    }
                }
                
                if (empty($shortCode)) {
                    // Генерация случайного кода
                    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                    do {
                        $shortCode = '';
                        for ($i = 0; $i < $codeLength; $i++) {
                            $shortCode .= $characters[random_int(0, strlen($characters) - 1)];
                        }
                        $stmt = $pdo->prepare("SELECT id FROM urls WHERE short_code = ?");
                        $stmt->execute([$shortCode]);
                    } while ($stmt->fetch());
                }
                
                // Сохранение ссылки
                $stmt = $pdo->prepare("INSERT INTO urls (short_code, original_url, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$shortCode, $originalUrl]);
                
                $message = 'Ссылка успешно создана: ' . htmlspecialchars($shortCode);
                $messageType = 'success';
                
                // Логирование
                $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['admin_id'], 'create_url', 'Создание ссылки ' . $shortCode, $_SERVER['REMOTE_ADDR']]);
            }
        } else {
            $message = 'Введите URL';
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM urls WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Ссылка удалена';
        $messageType = 'success';
        
        // Логирование
        $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], 'delete_url', 'Удаление ссылки ID ' . $_POST['id'], $_SERVER['REMOTE_ADDR']]);
    }
    
    if ($action === 'toggle_status' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE urls SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Статус изменен';
        $messageType = 'success';
    }
}

// Пагинация и поиск
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Получение ссылок
$where = '';
$params = [];
if ($search) {
    $where = "WHERE original_url LIKE ? OR short_code LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM urls $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$urls = $stmt->fetchAll();

// Всего записей
$stmt = $pdo->query("SELECT FOUND_ROWS() as total");
$totalUrls = $stmt->fetch()['total'];
$totalPages = ceil($totalUrls / $perPage);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все ссылки - Админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
        .sidebar h2 { margin-bottom: 30px; font-size: 1.5rem; }
        .sidebar nav a {
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        .sidebar nav a:hover, .sidebar nav a.active { background: #34495e; }
        .sidebar nav a.logout { background: #e74c3c; margin-top: 20px; }
        .main-content { padding: 30px; background: #f5f6fa; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .search-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-form input { flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .btn-primary { background: #3498db; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 15px; overflow: hidden; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #f8f9fa; font-weight: 600; }
        .actions { display: flex; gap: 5px; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a { padding: 10px 15px; background: white; border-radius: 8px; text-decoration: none; color: #333; }
        .pagination a.active { background: #3498db; color: white; }
        @media (max-width: 768px) {
            .admin-layout { grid-template-columns: 1fr; }
            .sidebar nav { display: flex; flex-wrap: wrap; gap: 10px; }
        }
    </style>
</head>
<body style="display: block; background: #f5f6fa;">
    <div class="admin-layout">
        <aside class="sidebar">
            <h2>🎛️ Админка</h2>
            <nav>
                <a href="index.php">📊 Дашборд</a>
                <a href="urls.php" class="active">🔗 Все ссылки</a>
                <a href="analytics.php">📈 Аналитика</a>
                <a href="settings.php">⚙️ Настройки</a>
                <a href="logs.php">📝 Логи</a>
                <a href="logout.php" class="logout">🚪 Выход</a>
            </nav>
        </aside>

        <main class="main-content">
            <h1>🔗 Управление ссылками</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Форма создания новой ссылки -->
            <div class="settings-card" style="margin-bottom: 20px;">
                <h2>➕ Создать новую ссылку</h2>
                <form method="POST" style="display: grid; gap: 15px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div style="grid-column: 1 / -1;">
                        <label for="original_url">Длинная ссылка (обязательно):</label>
                        <input type="url" id="original_url" name="original_url" placeholder="https://example.com/very-long-url" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                    
                    <?php if ($allowCustomAlias): ?>
                    <div>
                        <label for="custom_alias">Свой алиас (необязательно):</label>
                        <input type="text" id="custom_alias" name="custom_alias" placeholder="my-link" pattern="[a-zA-Z0-9_-]+" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <small style="color: #999;">Только буквы, цифры, _ и -</small>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label for="code_length">Длина случайного кода:</label>
                        <input type="number" id="code_length" name="code_length" min="4" max="20" value="<?php echo $defaultLength; ?>" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <small style="color: #999;">От 4 до 20 символов</small>
                    </div>
                    
                    <div style="grid-column: 1 / -1; display: flex; align-items: flex-end;">
                        <button type="submit" name="action" value="create" class="btn btn-primary" style="padding: 15px 30px; font-size: 16px;">Создать ссылку</button>
                    </div>
                </form>
            </div>

            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Поиск по URL или коду..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Найти</button>
                <a href="urls.php" class="btn btn-warning">Сброс</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Код</th>
                        <th>Оригинал</th>
                        <th>Переходов</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($urls as $url): 
                        $stmt = $pdo->prepare("SELECT COUNT(*) as clicks FROM url_stats WHERE url_id = ?");
                        $stmt->execute([$url['id']]);
                        $clicks = $stmt->fetch()['clicks'];
                    ?>
                    <tr>
                        <td><?php echo $url['id']; ?></td>
                        <td><a href="../<?php echo htmlspecialchars($url['short_code']); ?>" target="_blank"><?php echo htmlspecialchars($url['short_code']); ?></a></td>
                        <td style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($url['original_url']); ?></td>
                        <td><?php echo number_format($clicks); ?></td>
                        <td><?php echo $url['is_active'] ? '<span style="color: green;">● Активна</span>' : '<span style="color: red;">● Неактивна</span>'; ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($url['created_at'])); ?></td>
                        <td class="actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $url['id']; ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit" class="btn btn-warning"><?php echo $url['is_active'] ? 'Деактив.' : 'Актив.'; ?></button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить ссылку?');">
                                <input type="hidden" name="id" value="<?php echo $url['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
