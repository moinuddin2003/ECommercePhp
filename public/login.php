<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();
$db = new Database($conn);
$auth = new Auth($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        Session::flash('error', 'Please enter both email and password.');
        header('Location: index.php');
        exit;
    }

    $result = $auth->login($email, $password);

    if ($result['success']) {
        Session::flash('success', 'Login successful.');
        header('Location: index.php');
        exit;
    }

    Session::flash('error', $result['message']);
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
