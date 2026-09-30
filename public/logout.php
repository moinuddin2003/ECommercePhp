<?php
/**
 * public/logout.php
 * ------------------------------------------------
 * Signs the visitor OUT OF THE SHOP ONLY.
 *
 * It deliberately does NOT destroy the whole session, because you
 * might also be signed in to the admin panel. Wiping everything here
 * would log you out of admin too, which is not what "Logout" on a
 * shop page should do.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Auth.php';

Session::start();

$db = new Database($conn);
$auth = new Auth($db);

// 'customer' tells Auth to remove only the shop's keys.
$auth->logout('customer');

header('Location: index.php');
exit;