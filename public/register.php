<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();
$db = new Database($conn);
$auth = new Auth($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
        Session::flash('error', 'Please fill in all fields.');
        header('Location: index.php');
        exit;
    }

    if ($password !== $confirmPassword) {
        Session::flash('error', 'Passwords do not match.');
        header('Location: index.php');
        exit;
    }

    $result = $auth->register($name, $email, $password);

    if ($result['success']) {
        Session::flash('success', 'Registration successful. Please log in.');
        header('Location: index.php');
        exit;
    }

    Session::flash('error', $result['message']);
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
