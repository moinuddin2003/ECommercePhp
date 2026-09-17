<?php
/**
 * core/Session.php
 * ------------------------------------------------
 * A thin wrapper around PHP's $_SESSION so you don't
 * scatter session_start() / isset() checks everywhere.
 *
 * USAGE:
 *   Session::start();                        // call once, at the top of every page
 *   Session::set('user_id', 5);
 *   $id = Session::get('user_id');
 *   Session::flash('success', 'Order placed!'); // SET a flash message
 *   $msg = Session::flash('success');            // READ it (and it's removed after)
 */

class Session
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function remove($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Flash messages: set once, read once, then they auto-clear.
     * Perfect for "Order placed successfully!" after a redirect.
     *
     *   Session::flash('error', 'Something went wrong'); // to SET
     *   Session::flash('error');                          // to READ (and clear)
     */
    public static function flash($key, $message = null)
    {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }

        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }

        return null;
    }

    public static function destroy()
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_unset();
        session_destroy();
    }
}