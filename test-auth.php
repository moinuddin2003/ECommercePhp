<?php
/**
 * test-auth.php
 * ------------------------------------------------
 * Throwaway script to prove Session/Validator/Auth work
 * before wiring them into real register.php/login.php pages.
 * Delete this once it passes.
 *
 * Open it twice in the browser:
 *   1st time: registers + logs in a test user, shows session data.
 *   2nd time: since the email now exists, registration will
 *             correctly fail with "already registered" — that's expected.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Validator.php';
require_once __DIR__ . '/core/Auth.php';

Session::start();

$db = new Database($conn);
$auth = new Auth($db);

$testEmail = 'testuser@example.com';
$testPassword = 'secret123';

echo "<h2>1. Validator test</h2>";
$v = new Validator();
$v->required($testEmail, 'email')
  ->email($testEmail, 'email')
  ->required($testPassword, 'password')
  ->minLength($testPassword, 'password', 6);

echo $v->passes() ? "Validation passed ✅<br>" : "Validation failed: " . implode(', ', $v->errors()) . "<br>";

echo "<h2>2. Register test</h2>";
$result = $auth->register('Test User', $testEmail, $testPassword);
echo $result['message'] . "<br>";

echo "<h2>3. Login test</h2>";
$loginResult = $auth->login($testEmail, $testPassword);
echo $loginResult['message'] . "<br>";

if ($loginResult['success']) {
    echo "<h2>4. Session check</h2>";
    echo "user_id: " . Session::get('user_id') . "<br>";
    echo "user_name: " . Session::get('user_name') . "<br>";
    echo "user_role: " . Session::get('user_role') . "<br>";
    echo "Auth::isLoggedIn(): " . (Auth::isLoggedIn() ? 'true' : 'false') . "<br>";
    echo "Auth::isAdmin(): " . (Auth::isAdmin() ? 'true' : 'false') . "<br>";
}

echo "<h2>5. Logout test</h2>";
$auth->logout();
Session::start(); // logout destroys the session, so restart it for this test page
echo "Auth::isLoggedIn() after logout: " . (Auth::isLoggedIn() ? 'true' : 'false') . " (should be false)<br>";

echo "<h2>All good ✅ — clean up the test user row and delete test-auth.php when done</h2>";
echo "<p>To clean up: <code>DELETE FROM users WHERE email = 'testuser@example.com';</code></p>";