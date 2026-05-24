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

    if (!empty($data['biography']) && mb_strlen($data['biography']) > 1000) {
        $errors['biography'] = 'Пожелания не должны превышать 1000 символов.';
    }

    return $errors;
}

function saveNewApplication($data) {
    $pdo = getDbConnection();

    $login = bin2hex(random_bytes(8));
    $password = bin2hex(random_bytes(8));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql_app = "INSERT INTO application (full_name, phone, biography, login, password_hash)
                    VALUES (:full_name, :phone, :biography, :login, :password_hash)";
        $stmt_app = $pdo->prepare($sql_app);
        $stmt_app->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':biography' => $data['biography'],
            ':login' => $login,
            ':password_hash' => $password_hash
        ]);

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

    try {
        $sql_app = "UPDATE application SET full_name = :full_name, phone = :phone, biography = :biography WHERE id = :id";
        $stmt_app = $pdo->prepare($sql_app);
        $stmt_app->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':biography' => $data['biography'],
            ':id' => $app_id
        ]);

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
