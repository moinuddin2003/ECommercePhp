<?php
/**
 * core/Auth.php
 * ------------------------------------------------
 * Handles registration, login, logout, and the
 * "is this user allowed here?" checks.
 *
 * Depends on: core/Database.php, core/Session.php
 * (require both BEFORE this file).
 *
 * USAGE — registering a user:
 *   $auth = new Auth($db);
 *   $result = $auth->register('Ali', 'ali@example.com', 'secret123');
 *   if ($result['success']) { ... } else { echo $result['message']; }
 *
 * USAGE — logging in:
 *   $result = $auth->login($email, $password);
 *   if ($result['success']) {
 *       header('Location: index.php');
 *       exit;
 *   } else {
 *       echo $result['message'];
 *   }
 *
 * USAGE — protecting a page:
 *   Session::start();
 *   Auth::requireLogin();          // any storefront page that needs a logged-in user
 *   Auth::requireAdmin();          // any admin/* page
 */

class Auth
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Registers a new customer account.
     * Returns ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function register($name, $email, $password)
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM users WHERE email = ?',
            [$email],
            's'
        );

        if ($existing) {
            return ['success' => false, 'message' => 'That email is already registered.'];
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $id = $this->db->insert(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
            [$name, $email, $hashedPassword, 'customer'],
            'ssss'
        );

        return ['success' => true, 'message' => 'Account created.', 'id' => $id];
    }

    /**
     * Attempts to log a user in.
     * On success, stores user_id / user_name / user_role in the session
     * and regenerates the session id (guards against session fixation).
     */
    public function login($email, $password)
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = ?',
            [$email],
            's'
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        if ((int) $user['is_active'] === 0) {
            return ['success' => false, 'message' => 'This account has been deactivated.'];
        }

        // New session id on every login — prevents session fixation attacks
        session_regenerate_id(true);

        Session::set('user_id', $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);

        return ['success' => true, 'message' => 'Logged in.'];
    }

    public function logout()
    {
        Session::destroy();
    }

    public static function isLoggedIn()
    {
        return Session::has('user_id');
    }

    public static function isAdmin()
    {
        return Session::get('user_role') === 'admin';
    }

    /** Call at the top of any page that requires a logged-in customer. */
    public static function requireLogin($redirectTo = 'login.php')
    {
        if (!self::isLoggedIn()) {
            $current = $_SERVER['REQUEST_URI'] ?? '';
            $separator = strpos($redirectTo, '?') === false ? '?' : '&';
            header('Location: ' . $redirectTo . $separator . 'redirect=' . urlencode($current));
            exit;
        }
    }

    /** Call at the top of every admin/*.php page. */
    public static function requireAdmin($redirectTo = '/admin/login.php')
    {
        if (!self::isLoggedIn() || !self::isAdmin()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}