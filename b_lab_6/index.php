<?php

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        header('Location: form.php');
        exit();
    }

    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'biography' => trim($_POST['biography'] ?? ''),
        'agreement' => isset($_POST['agreement']) ? '1' : '',
        'languages' => $_POST['languages'] ?? []
    ];

    $errors = validateFormData($form_data);

    if (empty($errors)) {
        if (isset($_SESSION['user_id'])) {
            $db_success = updateApplication($_SESSION['user_id'], $form_data);
        } else {
            $db_success = saveNewApplication($form_data);
        }

        if ($db_success) {
            saveToCookies($form_data);
            setcookie('save', '1', time() + 24 * 60 * 60, '/');
            header('Location: form.php');
            exit();
        } else {
            header('Location: form.php');
            exit();
        }
    } else {
        saveToCookies($form_data, $errors);
        header('Location: form.php');
        exit();
    }
} else {
    header('Location: form.php');
    exit();
}
?>
