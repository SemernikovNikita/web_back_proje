<?php

function getDbConnection() {
    $host = 'localhost';
    $dbname = 'u82190';
    $user = 'u82190';
    $pass = '8528410';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function validateFormData($data) {
    $errors = array();

    if (empty($data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]{2,100}$/u', $data['full_name'])) {
        $errors['full_name'] = 'ФИО может содержать только буквы, пробелы и дефисы (2-100 символов).';
    }

    if (empty($data['phone'])) {
        $errors['phone'] = 'Номер телефона обязателен для заполнения.';
    } elseif (!preg_match('/^\+?[0-9\s\-\(\)]{10,20}$/', $data['phone'])) {
        $errors['phone'] = 'Введите корректный номер телефона. Допустимые символы: цифры, +, -, пробелы, скобки (10-20 символов).';
    }

    if (empty($data['email'])) {
        $errors['email'] = 'Email обязателен для заполнения.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $data['email'])) {
        $errors['email'] = 'Введите корректный email адрес (например: user@domain.com).';
    }

    if (empty($data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна для заполнения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birth_date'])) {
        $errors['birth_date'] = 'Неверный формат даты. Используйте формат ГГГГ-ММ-ДД.';
    } elseif ($data['birth_date'] > date('Y-m-d')) {
        $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
    } elseif (strtotime($data['birth_date']) < strtotime('-150 years')) {
        $errors['birth_date'] = 'Пожалуйста, укажите корректную дату рождения (не более 150 лет).';
    }

    if (empty($data['gender'])) {
        $errors['gender'] = 'Пожалуйста, выберите ваш пол.';
    } elseif (!in_array($data['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выбрано некорректное значение пола.';
    }

    if (!empty($data['biography']) && mb_strlen($data['biography']) > 1000) {
        $errors['biography'] = 'Биография не должна превышать 1000 символов.';
    }

    if (empty($data['agreement'])) {
        $errors['agreement'] = 'Необходимо подтвердить согласие с контрактом.';
    }

    if (empty($data['languages']) || !is_array($data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    }

    return $errors;
}

function saveToCookies($data, $errors = null) {
    $expire = time() + 365 * 24 * 60 * 60;

    setcookie('fio_value', $data['full_name'], $expire, '/');
    setcookie('phone_value', $data['phone'], $expire, '/');
    setcookie('email_value', $data['email'], $expire, '/');
    setcookie('birth_date_value', $data['birth_date'], $expire, '/');
    setcookie('gender_value', $data['gender'], $expire, '/');
    setcookie('biography_value', $data['biography'], $expire, '/');
    setcookie('agreement_value', $data['agreement'], $expire, '/');

    if (!empty($data['languages']) && is_array($data['languages'])) {
        setcookie('languages_value', implode(',', $data['languages']), $expire, '/');
    }

    if ($errors !== null && !empty($errors)) {
        $short_expire = time() + 24 * 60 * 60;
        foreach ($errors as $field => $error) {
            switch($field) {
                case 'full_name': setcookie('fio_error', '1', $short_expire, '/'); break;
                case 'phone': setcookie('phone_error', '1', $short_expire, '/'); break;
                case 'email': setcookie('email_error', '1', $short_expire, '/'); break;
                case 'birth_date': setcookie('birth_date_error', '1', $short_expire, '/'); break;
                case 'gender': setcookie('gender_error', '1', $short_expire, '/'); break;
                case 'biography': setcookie('biography_error', '1', $short_expire, '/'); break;
                case 'agreement': setcookie('agreement_error', '1', $short_expire, '/'); break;
                case 'languages': setcookie('languages_error', '1', $short_expire, '/'); break;
            }
        }
    }
}

function initCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function saveNewApplication($data) {
    $pdo = getDbConnection();

    $login = bin2hex(random_bytes(8));
    $password = bin2hex(random_bytes(8));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $languages_db = [];
    $stmt = $pdo->query("SELECT id, name FROM programming_language ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $languages_db[$row['name']] = $row['id'];
    }

    try {
        $pdo->beginTransaction();

        $sql_app = "INSERT INTO application (full_name, phone, email, birth_date, gender, biography, agreement, login, password_hash)
                    VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :agreement, :login, :password_hash)";
        $stmt_app = $pdo->prepare($sql_app);
        $stmt_app->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':biography' => $data['biography'],
            ':agreement' => $data['agreement'],
            ':login' => $login,
            ':password_hash' => $password_hash
        ]);

        $application_id = $pdo->lastInsertId();

        $sql_link = "INSERT INTO application_language (application_id, language_id) VALUES (?, ?)";
        $stmt_link = $pdo->prepare($sql_link);

        foreach ($data['languages'] as $lang_name) {
            if (isset($languages_db[$lang_name])) {
                $stmt_link->execute([$application_id, $languages_db[$lang_name]]);
            }
        }

        $pdo->commit();

        $_SESSION['credentials'] = ['login' => $login, 'password' => $password];
        $_SESSION['success'] = true;
        unset($_SESSION['form_data']);
        unset($_SESSION['errors']);

        return true;

    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        $_SESSION['errors']['general'] = 'Ошибка сохранения данных: ' . $e->getMessage();
        $_SESSION['form_data'] = $data;
        return false;
    }
}

function updateApplication($app_id, $data) {
    $pdo = getDbConnection();

    $languages_db = [];
    $stmt = $pdo->query("SELECT id, name FROM programming_language ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $languages_db[$row['name']] = $row['id'];
    }

    try {
        $pdo->beginTransaction();

        $sql_app = "UPDATE application SET full_name = :full_name, phone = :phone, email = :email, birth_date = :birth_date,
                    gender = :gender, biography = :biography, agreement = :agreement WHERE id = :id";
        $stmt_app = $pdo->prepare($sql_app);
        $stmt_app->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':biography' => $data['biography'],
            ':agreement' => $data['agreement'],
            ':id' => $app_id
        ]);

        $stmt = $pdo->prepare("DELETE FROM application_language WHERE application_id = ?");
        $stmt->execute([$app_id]);

        $sql_link = "INSERT INTO application_language (application_id, language_id) VALUES (?, ?)";
        $stmt_link = $pdo->prepare($sql_link);

        foreach ($data['languages'] as $lang_name) {
            if (isset($languages_db[$lang_name])) {
                $stmt_link->execute([$app_id, $languages_db[$lang_name]]);
            }
        }

        $pdo->commit();

        $_SESSION['success'] = true;
        unset($_SESSION['form_data']);
        unset($_SESSION['errors']);

        return true;

    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        $_SESSION['errors']['general'] = 'Ошибка обновления данных: ' . $e->getMessage();
        $_SESSION['form_data'] = $data;
        return false;
    }
}
