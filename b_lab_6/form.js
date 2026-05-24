document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    if (!form) return;

    var apiUrl = form.getAttribute('data-api-url');
    if (!apiUrl) return;

    var messagesContainer = document.getElementById('api-messages');

    function showMessage(type, html) {
        if (!messagesContainer) return;
        var div = document.createElement('div');
        div.className = 'msg-box ' + (type === 'success' ? 'msg-success' : 'msg-error');
        div.innerHTML = html;
        messagesContainer.appendChild(div);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        messagesContainer.innerHTML = '';

        var data = {};
        data.name = document.getElementById('name')?.value || '';
        data.phone = document.getElementById('phone')?.value || '';
        data.bouquet = document.getElementById('bouquet')?.value || '';
        data.message = document.getElementById('message')?.value || '';

        if (!data.name) { showMessage('msg-error', 'Пожалуйста, введите ваше имя.'); return; }
        if (!data.phone) { showMessage('msg-error', 'Пожалуйста, введите ваш телефон.'); return; }

        var headers = { 'Content-Type': 'application/json' };
        var userLogin = form.getAttribute('data-login');
        if (userLogin) {
            var userPassword = document.getElementById('api_password')?.value;
            if (!userPassword) {
                alert('Введите пароль для подтверждения изменений.');
                return;
            }
            headers['Authorization'] = 'Basic ' + btoa(userLogin + ':' + userPassword);
        }

        var submitBtn = form.querySelector('[type="submit"]');
        var originalText = submitBtn ? submitBtn.textContent : 'Отправить';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Отправка...';
        }

        try {
            var response = await fetch(apiUrl, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(data)
            });

            var result = await response.json();

            if (result.success) {
                if (result.login && result.password) {
                    showMessage('success',
                        'Спасибо, результаты сохранены.<br>' +
                        'Ваш логин: <b>' + result.login + '</b><br>' +
                        'Ваш пароль: <b>' + result.password + '</b><br>' +
                        'Адрес профиля: <a href="' + result.profile_url + '">' + result.profile_url + '</a>'
                    );
                } else {
                    showMessage('success', 'Данные успешно обновлены.');
                }
                form.reset();
            } else {
                var errorText = '';
                if (result.errors) {
                    if (typeof result.errors === 'object') {
                        for (var key in result.errors) {
                            if (result.errors.hasOwnProperty(key)) {
                                errorText += result.errors[key] + '<br>';
                            }
                        }
                    } else {
                        errorText = result.errors;
                    }
                } else if (result.message) {
                    errorText = result.message;
                }
                showMessage('error', errorText || 'Произошла ошибка при отправке.');
            }
        } catch (error) {
            showMessage('error', 'Ошибка соединения: ' + error.message);
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
});
