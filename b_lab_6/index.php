<?php

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/functions.php';

function mapFrontendFields($raw) {
    $mapped = [];
    $mapped['full_name'] = trim($raw['name'] ?? '');

    $bouquet = trim($raw['bouquet'] ?? '');
    $message = trim($raw['message'] ?? '');
    $bio = '';
    if (!empty($bouquet)) {
        $bio .= 'Выбранный букет: ' . $bouquet;
    }
    if (!empty($message)) {
        if (!empty($bio)) $bio .= "\n";
        $bio .= 'Пожелания: ' . $message;
    }
    $mapped['biography'] = $bio;
    $mapped['phone'] = trim($raw['phone'] ?? '');

    return $mapped;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'bouquet' => trim($_POST['bouquet'] ?? ''),
        'message' => trim($_POST['message'] ?? '')
    ];

    $mapped = mapFrontendFields($form_data);

    $errors = validateFormData($mapped);

    if (empty($errors)) {
        if (isset($_SESSION['user_id'])) {
            $db_success = updateApplication($_SESSION['user_id'], $mapped);
        } else {
            $db_success = saveNewApplication($mapped);
        }

        if ($db_success) {
            setcookie('save', '1', time() + 24 * 60 * 60, '/');
            header('Location: form.php');
            exit();
        } else {
            $_SESSION['errors'] = ['general' => 'Ошибка сохранения данных.'];
            header('Location: form.php');
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        header('Location: form.php');
        exit();
    }
} else {
    header('Location: form.php');
    exit();
}
