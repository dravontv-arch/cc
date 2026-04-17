<?php
// api/shorten.php - API для сокращения ссылок

header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Метод не разрешен';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$originalUrl = isset($input['url']) ? trim($input['url']) : '';
$customAlias = isset($input['alias']) ? trim($input['alias']) : null;
$length = isset($input['length']) ? (int)$input['length'] : DEFAULT_SHORT_LENGTH;

if (empty($originalUrl)) {
    $response['message'] = 'URL обязателен';
    echo json_encode($response);
    exit;
}

// Добавляем протокол если нет
if (!preg_match('~^(?:f|ht)tps?://~i', $originalUrl)) {
    $originalUrl = 'http://' . $originalUrl;
}

if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
    $response['message'] = 'Некорректный URL';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Генерация короткого кода
    if ($customAlias) {
        // Проверка пользовательского алиаса
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $customAlias)) {
            $response['message'] = 'Алиас может содержать только буквы, цифры, _ и -';
            echo json_encode($response);
            exit;
        }
        
        // Проверка на существование
        $stmt = $pdo->prepare("SELECT id FROM urls WHERE short_code = ?");
        $stmt->execute([$customAlias]);
        if ($stmt->fetch()) {
            $response['message'] = 'Такой алиас уже используется';
            echo json_encode($response);
            exit;
        }
        $shortCode = $customAlias;
    } else {
        // Генерация случайного кода
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $shortCode = '';
        
        for ($i = 0; $i < $length; $i++) {
            $shortCode .= $characters[rand(0, $charactersLength - 1)];
        }
        
        // Проверка уникальности
        $stmt = $pdo->prepare("SELECT id FROM urls WHERE short_code = ?");
        $stmt->execute([$shortCode]);
        if ($stmt->fetch()) {
            // Рекурсивная генерация если код занят
            $response['message'] = 'Код занят, попробуйте снова';
            echo json_encode($response);
            exit;
        }
    }
    
    // Сохранение в БД
    $stmt = $pdo->prepare("INSERT INTO urls (original_url, short_code) VALUES (?, ?)");
    $stmt->execute([$originalUrl, $shortCode]);
    $urlId = $pdo->lastInsertId();
    
    $shortUrl = getBaseUrl() . '/' . $shortCode;
    
    $response['success'] = true;
    $response['message'] = 'Ссылка успешно сокращена';
    $response['data'] = [
        'id' => $urlId,
        'original_url' => $originalUrl,
        'short_code' => $shortCode,
        'short_url' => $shortUrl
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Ошибка базы данных: ' . $e->getMessage();
}

echo json_encode($response);
?>
