document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    if (!form) return;

    var apiUrl = form.getAttribute('data-api-url');
    if (!apiUrl) return;

    var messagesContainer = document.getElementById('api-messages');

    function showMessage(type, html) {
        if (!messagesContainer) return;
        var div = document.createElement('div');
        div.className = type === 'success' ? 'success-message' : 'error-message';
        div.style.cssText = (type === 'success'
            ? 'color: green; background-color: #e8f5e9; border: 1px solid green;'
            : 'color: red; background-color: #ffebee; border: 1px solid red;')
            + ' padding: 10px; margin-bottom: 20px; border-radius: 5px;';
        div.innerHTML = html;
        messagesContainer.appendChild(div);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        messagesContainer.innerHTML = '';

        var data = {};
        data.full_name = form.querySelector('[name="full_name"]')?.value || '';
        data.phone = form.querySelector('[name="phone"]')?.value || '';
        data.email = form.querySelector('[name="email"]')?.value || '';
        data.birth_date = form.querySelector('[name="birth_date"]')?.value || '';
        var genderEl = form.querySelector('[name="gender"]:checked');
        data.gender = genderEl ? genderEl.value : '';
        data.biography = form.querySelector('[name="biography"]')?.value || '';
        data.agreement = form.querySelector('[name="agreement"]')?.checked ? '1' : '';

        var langSelect = form.querySelector('[name="languages[]"]');
        data.languages = [];
        if (langSelect) {
            for (var i = 0; i < langSelect.options.length; i++) {
                if (langSelect.options[i].selected) {
                    data.languages.push(langSelect.options[i].value);
                }
            }
        }

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
        var originalText = submitBtn ? submitBtn.value : 'Сохранить';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.value = 'Отправка...';
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
                submitBtn.value = originalText;
            }
        }
    });
});
