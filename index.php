<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Shortener - Сокращение ссылок</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔗 URL Shortener</h1>
            <p class="subtitle">Сократите вашу ссылку за секунду</p>
        </header>

        <main>
            <form id="shortenForm" class="shorten-form">
                <div class="form-group">
                    <label for="originalUrl">Введите длинную ссылку:</label>
                    <input type="url" id="originalUrl" name="url" placeholder="https://example.com/very-long-url" required>
                </div>

                <div class="options-toggle">
                    <button type="button" id="toggleOptions">⚙️ Дополнительные настройки</button>
                </div>

                <div id="advancedOptions" class="advanced-options" style="display: none;">
                    <div class="form-group">
                        <label for="customAlias">Свой алиас (необязательно):</label>
                        <input type="text" id="customAlias" name="alias" placeholder="my-custom-link" pattern="[a-zA-Z0-9_-]+">
                        <small>Только буквы, цифры, _ и -</small>
                    </div>

                    <div class="form-group">
                        <label for="linkLength">Длина случайной ссылки:</label>
                        <input type="number" id="linkLength" name="length" min="4" max="20" value="6">
                        <small>От 4 до 20 символов</small>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Сократить ссылку</button>
            </form>

            <div id="result" class="result" style="display: none;">
                <div class="result-success">
                    <h3>✅ Ссылка успешно сокращена!</h3>
                    <div class="short-url-container">
                        <input type="text" id="shortUrlOutput" readonly>
                        <button type="button" id="copyBtn" class="btn-copy">Копировать</button>
                    </div>
                    <p class="original-url">Оригинал: <span id="originalUrlOutput"></span></p>
                </div>
            </div>

            <div id="error" class="error" style="display: none;">
                <p id="errorMessage"></p>
            </div>
        </main>

        <footer>
            <p><a href="admin/login.php">Админ-панель</a></p>
        </footer>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
