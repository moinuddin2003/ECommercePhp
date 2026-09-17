<?php
/**
 * public/logout.php
 * ------------------------------------------------
 * Destroys the session and sends the user back to the homepage.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();

$db = new Database($conn);
$auth = new Auth($db);
$auth->logout();

header('Location: index.php');
exit;