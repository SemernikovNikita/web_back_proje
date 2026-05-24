document.addEventListener('DOMContentLoaded', function() {
    var nav = document.querySelector('nav');
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var body = document.body;
    
    function toggleBurgerMenu(state) {
        if (!nav || window.innerWidth > 768) return;
        if (state === 'open' || (!nav.classList.contains('active') && nav.style.display !== 'block')) {
            nav.style.display = 'block';
            setTimeout(function() { nav.classList.add('active'); }, 10);
            body.classList.add('menu-open');
            var firstLink = nav.querySelector('a');
            if (firstLink) firstLink.focus();
        } else {
            nav.classList.remove('active');
            setTimeout(function() {
                nav.style.display = 'none';
                body.classList.remove('menu-open');
            }, 300);
        }
    }
    
    if (mobileMenuBtn && nav) {
        nav.style.display = window.innerWidth <= 768 ? 'none' : 'flex';
        
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleBurgerMenu();
        });
        
        document.querySelectorAll('nav a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (window.innerWidth <= 768) {
                    toggleBurgerMenu('close');
                    if (href && href.charAt(0) === '#') {
                        e.preventDefault();
                        setTimeout(function() {
                            var target = document.querySelector(href);
                            if (target) {
                                var headerHeight = document.querySelector('header').offsetHeight;
                                var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                                window.scrollTo({top: targetPosition, behavior: 'smooth'});
                            }
                        }, 350);
                    }
                }
            });
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && nav.classList.contains('active') &&
                !nav.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                toggleBurgerMenu('close');
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && nav.classList.contains('active')) {
                toggleBurgerMenu('close');
                mobileMenuBtn.focus();
            }
        });
    }
    
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
        
        document.getElementById('nextBtn').addEventListener('click', function() {
            clearInterval(slideInterval);
            goToSlide(currentSlide + 1, false);
            slideInterval = setInterval(function() { goToSlide(currentSlide + 1, true); }, 5000);
        });
        
        document.getElementById('prevBtn').addEventListener('click', function() {
            clearInterval(slideInterval);
            goToSlide(currentSlide - 1, false);
            slideInterval = setInterval(function() { goToSlide(currentSlide + 1, true); }, 5000);
        });
    }
    
    var feedbackForm = document.getElementById('orderForm');
    var messagesContainer = document.getElementById('api-messages');
    
    if (feedbackForm) {
        function showMessage(type, html) {
            if (!messagesContainer) return;
            var div = document.createElement('div');
            div.className = 'form-message';
            div.style.cssText = 'display:block; padding:15px; margin-bottom:20px; border-radius:8px; text-align:center; font-weight:500;' +
                (type === 'success'
                    ? 'background:#d4edda; color:#155724; border:1px solid #c3e6cb;'
                    : 'background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;');
            div.innerHTML = html;
            messagesContainer.innerHTML = '';
            messagesContainer.appendChild(div);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        feedbackForm.addEventListener('submit', async function(e) {
            var apiUrl = feedbackForm.getAttribute('data-api-url');
            if (!apiUrl) return;
            e.preventDefault();
            
            var data = {};
            data.name = document.getElementById('name') ? document.getElementById('name').value : '';
            data.phone = document.getElementById('phone') ? document.getElementById('phone').value : '';
            data.bouquet = document.getElementById('bouquet') ? document.getElementById('bouquet').value : '';
            data.message = document.getElementById('message') ? document.getElementById('message').value : '';
            
            if (!data.name) { showMessage('error', 'Пожалуйста, введите ваше имя.'); return; }
            if (!data.phone) { showMessage('error', 'Пожалуйста, введите ваш телефон.'); return; }
            
            var headers = { 'Content-Type': 'application/json' };
            
            var submitBtn = feedbackForm.querySelector('button[type="submit"]');
            var originalText = submitBtn ? submitBtn.textContent : 'Отправить';
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
                            'Профиль: <a href="' + result.profile_url + '" style="color:#155724;">' + result.profile_url + '</a>'
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
    
    document.querySelectorAll('a[href^="#"]:not(nav a)').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            var targetId = this.getAttribute('href');
            var targetElement = document.querySelector(targetId);
            if (targetElement && targetId !== '#') {
                e.preventDefault();
                var headerHeight = document.querySelector('header').offsetHeight;
                var targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                window.scrollTo({top: targetPosition, behavior: 'smooth'});
            }
        });
    });
    
    window.addEventListener('resize', function() {
        if (!nav) return;
        if (window.innerWidth > 768) {
            nav.style.display = 'flex';
            nav.classList.remove('active');
            body.classList.remove('menu-open');
        } else if (!nav.classList.contains('active')) {
            nav.style.display = 'none';
        }
    });
    
    window.toggleBurgerMenu = toggleBurgerMenu;
});
