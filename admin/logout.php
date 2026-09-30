<?php
/**
 * admin/logout.php
 * ------------------------------------------------
 * Signs the admin OUT OF THE ADMIN PANEL ONLY.
 *
 * It used to call Session::destroy(), which threw away the ENTIRE
 * session -- so logging out of the admin panel also kicked you out of
 * the shop. Now it only removes the admin's own keys.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();

$db = new Database($conn);
$auth = new Auth($db);

// 'admin' tells Auth to remove only the admin panel's keys.
$auth->logout('admin');

header('Location: login.php');
exit;