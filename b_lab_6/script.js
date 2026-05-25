document.addEventListener('DOMContentLoaded', function() {
    var slider = document.getElementById('slider');
    var sliderDots = document.getElementById('sliderDots');

    if (slider && sliderDots) {
        var currentSlide = 0;
        var slides = document.querySelectorAll('.slide');
        var totalSlides = slides.length;

        for (var i = 0; i < totalSlides; i++) {
            var dot = document.createElement('div');
            dot.classList.add('dot');
            if (i === 0) dot.classList.add('active');
            dot.addEventListener('click', function(idx) { return function() { goToSlide(idx, false); }; }(i));
            sliderDots.appendChild(dot);
        }

        var dots = document.querySelectorAll('.dot');

        function goToSlide(n, animate) {
            currentSlide = (n + totalSlides) % totalSlides;
            slider.style.transition = animate !== false ? 'transform 0.3s ease' : 'none';
            slider.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
            if (animate === false) {
                setTimeout(function() { slider.style.transition = 'transform 0.3s ease'; }, 10);
            }
            dots.forEach(function(d) { d.classList.remove('active'); });
            if (dots[currentSlide]) dots[currentSlide].classList.add('active');
        }

        var slideInterval = setInterval(function() { goToSlide(currentSlide + 1, true); }, 5000);

        var prevBtn = document.getElementById('prevBtn');
        var nextBtn = document.getElementById('nextBtn');
        if (prevBtn) prevBtn.addEventListener('click', function() {
            clearInterval(slideInterval);
            goToSlide(currentSlide - 1, false);
            slideInterval = setInterval(function() { goToSlide(currentSlide + 1, true); }, 5000);
        });
        if (nextBtn) nextBtn.addEventListener('click', function() {
            clearInterval(slideInterval);
            goToSlide(currentSlide + 1, false);
            slideInterval = setInterval(function() { goToSlide(currentSlide + 1, true); }, 5000);
        });
    }

    var feedbackForm = document.getElementById('orderForm');

    if (feedbackForm) {
        function showMessage(type, html) {
            var existing = document.querySelector('.api-message-box');
            if (existing) existing.remove();
            var div = document.createElement('div');
            div.className = 'api-message-box';
            div.style.cssText = 'display:block; padding:15px; margin-bottom:20px; border-radius:8px; text-align:center; font-weight:500;' +
                (type === 'success'
                    ? 'background:#1a2e1a; color:#d4edda; border:1px solid #28a745;'
                    : 'background:#2d1b1e; color:#f8d7da; border:1px solid #dc3545;');
            div.innerHTML = html;
            var contactForm = document.querySelector('.contact-form');
            if (contactForm) {
                contactForm.insertBefore(div, contactForm.firstChild);
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        feedbackForm.addEventListener('submit', async function(e) {
            var apiUrl = window.API_URL;
            if (!apiUrl) return;
            e.preventDefault();

            var data = {};
            data.name = document.getElementById('name') ? document.getElementById('name').value : '';
            data.phone = document.getElementById('phone') ? document.getElementById('phone').value : '';

            var bouquetChecks = document.querySelectorAll('input[name="bouquets[]"]:checked');
            data.bouquets = [];
            bouquetChecks.forEach(function(cb) { data.bouquets.push(cb.value); });

            data.message = document.getElementById('message') ? document.getElementById('message').value : '';

            if (!data.name) { showMessage('error', 'Пожалуйста, введите ваше имя.'); return; }
            if (!data.phone) { showMessage('error', 'Пожалуйста, введите ваш телефон.'); return; }

            var submitBtn = feedbackForm.querySelector('button[type="submit"]');
            var originalText = submitBtn ? submitBtn.textContent : 'Отправить';

            var headers = { 'Content-Type': 'application/json' };
            if (window.IS_LOGGED_IN && window.USER_LOGIN && window.USER_PASSWORD) {
                var authString = btoa(window.USER_LOGIN + ':' + window.USER_PASSWORD);
                headers['Authorization'] = 'Basic ' + authString;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Отправка...'; }

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
                            'Спасибо, заказ принят.<br>' +
                            'Ваш логин: <b>' + result.login + '</b><br>' +
                            'Ваш пароль: <b>' + result.password + '</b><br>' +
                            'Профиль: <a href="' + result.profile_url + '" style="color:#d4edda;">' + result.profile_url + '</a>'
                        );
                    } else {
                        showMessage('success', 'Данные успешно обновлены.');
                    }
                    feedbackForm.reset();
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
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
            }
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            var targetId = this.getAttribute('href');
            var targetElement = document.querySelector(targetId);
            if (targetElement && targetId !== '#') {
                e.preventDefault();
                var headerHeight = document.querySelector('header').offsetHeight;
                var targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                window.scrollTo({ top: targetPosition, behavior: 'smooth' });
            }
        });
    });
});
