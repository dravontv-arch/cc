// assets/js/main.js - Основной JavaScript для главной страницы

document.addEventListener('DOMContentLoaded', function() {
    const shortenForm = document.getElementById('shortenForm');
    const toggleOptionsBtn = document.getElementById('toggleOptions');
    const advancedOptions = document.getElementById('advancedOptions');
    const resultDiv = document.getElementById('result');
    const errorDiv = document.getElementById('error');
    const errorMessage = document.getElementById('errorMessage');
    const shortUrlOutput = document.getElementById('shortUrlOutput');
    const originalUrlOutput = document.getElementById('originalUrlOutput');
    const copyBtn = document.getElementById('copyBtn');

    // Переключение дополнительных настроек
    toggleOptionsBtn.addEventListener('click', function() {
        if (advancedOptions.style.display === 'none') {
            advancedOptions.style.display = 'block';
        } else {
            advancedOptions.style.display = 'none';
        }
    });

    // Обработка формы
    shortenForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Сброс предыдущих результатов
        resultDiv.style.display = 'none';
        errorDiv.style.display = 'none';

        // Получение данных формы
        const url = document.getElementById('originalUrl').value.trim();
        const alias = document.getElementById('customAlias').value.trim();
        const length = document.getElementById('linkLength').value;

        // Валидация
        if (!url) {
            showError('Пожалуйста, введите URL');
            return;
        }

        // Блокировка кнопки
        const submitBtn = shortenForm.querySelector('.btn-submit');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner"></span>Обработка...';
        submitBtn.disabled = true;
        shortenForm.classList.add('loading');

        try {
            const response = await fetch('api/shorten.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    url: url,
                    alias: alias || null,
                    length: parseInt(length) || 6
                })
            });

            const data = await response.json();

            if (data.success) {
                showResult(data.data);
            } else {
                showError(data.message || 'Произошла ошибка при сокращении ссылки');
            }
        } catch (error) {
            showError('Ошибка сети: ' + error.message);
        } finally {
            // Разблокировка кнопки
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            shortenForm.classList.remove('loading');
        }
    });

    // Показ результата
    function showResult(data) {
        shortUrlOutput.value = data.short_url;
        originalUrlOutput.textContent = data.original_url;
        resultDiv.style.display = 'block';
    }

    // Показ ошибки
    function showError(message) {
        errorMessage.textContent = message;
        errorDiv.style.display = 'block';
    }

    // Копирование в буфер обмена
    copyBtn.addEventListener('click', function() {
        shortUrlOutput.select();
        shortUrlOutput.setSelectionRange(0, 99999); // Для мобильных устройств

        navigator.clipboard.writeText(shortUrlOutput.value).then(function() {
            const originalText = copyBtn.textContent;
            copyBtn.textContent = '✅ Скопировано!';
            setTimeout(function() {
                copyBtn.textContent = originalText;
            }, 2000);
        }).catch(function(err) {
            // Fallback для старых браузеров
            document.execCommand('copy');
            const originalText = copyBtn.textContent;
            copyBtn.textContent = '✅ Скопировано!';
            setTimeout(function() {
                copyBtn.textContent = originalText;
            }, 2000);
        });
    });
});
