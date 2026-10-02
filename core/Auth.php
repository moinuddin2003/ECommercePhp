<?php
/**
 * core/Auth.php  --  PLAIN ENGLISH VERSION
 * ===================================================================
 * WHAT IS THIS FILE?
 * It answers 4 questions about a visitor:
 *
 *      1. Can I create an account?          -> register()
 *      2. Can I come back later?             -> login()
 *      3. Am I signed in right now?          -> isLoggedIn()
 *      4. Am I the shop owner (admin)?       -> isAdmin()
 *
 * ...plus two "door guards" that bounce people away:
 *
 *      requireLogin()   "you must be signed in to see this page"
 *      requireAdmin()   "you must be the admin to see this page"
 *
 * ===================================================================
 * THE BIG IDEA: WHY DOES LOGIN EVEN WORK?
 * -------------------------------------------------------------------
 * PHP forgets everything at the end of every page. When you click
 * "Sign in" on login.php, that page ends and dies. So how does the
 * NEXT page know who you are?
 *
 * Answer: the SESSION. It is a storage box that lives on the server
 * and keeps your files open from page to page. Session.php is the
 * helper that writes to it.
 *
 * So logging in really means just 3 things:
 *
 *      1. check the email + password are correct
 *      2. write "user id 5, name Ali, role customer" into the session
 *      3. on later pages, read it back out
 *
 * That is why every protected page asks the SAME question at the top:
 *
 *      Auth::requireLogin();
 *
 * ===================================================================
 * THE ONE MYSQL THING TO KNOW
 * -------------------------------------------------------------------
 * Passwords are NOT stored as typed. "secret123" is never saved.
 * Instead it is scrambled by password_hash() and THAT is saved:
 *
 *      $2y$10$Xy8...long mess...
 *
 * When you log in, password_verify() scrambles what you typed and
 * compares it to the stored mess. If they match, it is the right
 * password. This is the correct pair of functions to use and you
 * never need to understand the maths.
 * ===================================================================
 */

class Auth
{
    /**
     * We keep the database object here so our methods can
     * do $this->db->fetchOne(...) without being passed it again.
     */
    private $db;

    /**
     * The type hint "Database $db" means: this REQUIRES a Database
     * object. If you pass anything else, PHP stops with a clear
     * error instead of failing mysteriously later.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * register() = create a new customer account
     *
     * It does exactly 3 things:
     *      1. check nobody already has this email
     *      2. scramble the password
     *      3. save the row
     *
     * RETURNS the same shape every time, which is why calling pages
     * can always do this:
     *
     *      $result = $auth->register('Ali', 'ali@example.com', 'secret123');
     *      if ($result['success']) {
     *          // worked
     *      } else {
     *          echo $result['message'];   // why it did not work
     *      }
     */
    public function register($name, $email, $password)
    {
        // ---- STEP 1: is this email already taken? ----
        $existing = $this->db->fetchOne(
            'SELECT id FROM users WHERE email = ?',
            [$email]
        );

        // $existing is either null (email is free) or an array like
        // ['id' => '3'] (already taken). An array is "truthy",
        // null is "falsy", so this if does the right thing.
        if ($existing) {
            return ['success' => false, 'message' => 'That email is already registered.'];
        }

        // ---- STEP 2: scramble the password ----
        // PASSWORD_BCRYPT just means "use the good modern algorithm".
        // The real value is random every time (that is the "salt"),
        // which is why two identical passwords give different results.
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // ---- STEP 3: save it ----
        // Note the role 'customer' is written HERE, by us.
        // We never take the role from the form, otherwise a visitor
        // could tick a hidden box and make themselves an admin.
        $id = $this->db->insert(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
            [$name, $email, $hashedPassword, 'customer']
        );

        return ['success' => true, 'message' => 'Account created.', 'id' => $id];
    }

    /**
     * login() = try to sign in
     *
     * @param string $area  'admin' or 'customer' -- WHICH side of the
     *                      shop is signing in. The login is saved under
     *                      that area's own keys, so an admin login never
     *                      overwrites (or gets overwritten by) a
     *                      customer login.
     *
     * STEP BY STEP:
     *      1. find the account by email
     *      2. check the password is right
     *      3. check the account has not been switched off
     *      4. check the role suits this area (admin vs customer)
     *      5. save the login under this area's own keys
     *
     *   $auth->login($email, $password, 'admin');      // admin/login.php
     *   $auth->login($email, $password, 'customer');   // public/login.php
     */
    public function login($email, $password, $area = 'customer')
    {
        // ---- STEP 1: find the row ----
        // SELECT * because we need several columns below:
        // password, is_active, id, name, role.
        $user = $this->db->fetchOne(
            'SELECT * FROM users WHERE email = ?',
            [$email]
        );

        // ---- STEP 2: is the password right? ----
        // Two ways to fail, and we want ONE message for both.
        //
        //   !$user                  -> no such email at all
        //   !password_verify(...)   -> email fine, password wrong
        //
        // We say the same thing either way ("Incorrect email or password")
        // so the form does not reveal which emails are real accounts.
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        // ---- STEP 3: has the admin switched this account off? ----
        // The (int) matters. MySQL hands back the text '0', not the
        // number 0, and '0' === 0 is FALSE in PHP. Casting turns the
        // text into a real number so the check works.
        if ((int) $user['is_active'] === 0) {
            return ['success' => false, 'message' => 'This account has been deactivated.'];
        }

        // ---- STEP 4: remember who this is, AND where they logged in ----
        // ---- STEP 4: is this the RIGHT area for this account? ----
        // 'admin' can only be used by an admin, 'customer' only by a
        // customer. This stops a normal customer from signing in at
        // the admin login form.
        if ($user['role'] !== $area) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        // ---- STEP 5: save the login under its OWN area's keys ----
        // THIS IS THE IMPORTANT PART.
        //
        // Before, both areas saved to the same keys (user_id,
        // user_name, user_role). So if you signed in as an admin and
        // then opened the shop, the shop thought an admin was using
        // it, and your customer login looked "already done".
        //
        // Now each area has its own set of keys:
        //
        //   shop   -> customer_id  / customer_name  / customer_role
        //   admin  -> admin_id     / admin_name     / admin_role
        //
        // Two separate logins can now live in the same browser at the
        // same time without fighting each other.
        $prefix = $area === 'admin' ? 'admin_' : 'customer_';

        // Make sure a session is really open before we write to it.
        Session::start();

        // A brand new session id at the exact moment of signing in
        // stops anyone from planting a known id beforehand and
        // hijacking the session afterwards.
        session_regenerate_id(true);

        Session::set($prefix . 'id', $user['id']);
        Session::set($prefix . 'name', $user['name']);
        Session::set($prefix . 'role', $user['role']);

        if ($area === 'customer') {
            require_once __DIR__ . '/Cart.php';
            Cart::restoreForUser($this->db, $user['id']);
        }

        return ['success' => true, 'message' => 'Logged in.'];
    }

    /**
     * logout($area) = sign out of ONE area
     *
     * Note it only removes that area's keys. If you were signed in as
     * an admin AND a customer, logging out of the shop leaves your
     * admin session alone, and vice versa.
     *
     *   $auth->logout('admin');     // sign out of admin only
     *   $auth->logout('customer');  // sign out of the shop only
     */
    public function logout($area = 'customer')
    {
        $prefix = $area === 'admin' ? 'admin_' : 'customer_';

        Session::remove($prefix . 'id');
        Session::remove($prefix . 'name');
        Session::remove($prefix . 'role');
        if ($area !== 'admin') {
            Session::remove('cart');
        }
    }

    /* ==================================================================
       THE "AM I ALLOWED IN?" QUESTIONS
       ------------------------------------------------------------------
       These are STATIC. "static" means you call them on the class
       name, with no object in your hand:

           Auth::isLoggedIn()
           Auth::isAdminLoggedIn()

       (A non-static method would need an object: $auth->isLoggedIn().)
       A static method cannot use $this, which is fine here because
       these only read the session -- they never touch the database.
       ================================================================== */

    /**
     * isLoggedIn($area) = "is anyone signed in HERE?"
     *
     *   Auth::isLoggedIn()            -> the shop   (customer)
     *   Auth::isLoggedIn('admin')     -> the admin panel
     *
     * Each area looks for its own key, so signing in to one does not
     * make you look signed in to the other.
     */
    public static function isLoggedIn($area = 'customer')
    {
        $prefix = $area === 'admin' ? 'admin_' : 'customer_';

        return Session::has($prefix . 'id');
    }

    /**
     * isAdminLoggedIn() = "is the ADMIN PANEL signed in?"
     *
     * Not just signed in -- the admin area's role must be 'admin'.
     * This is the one to use for the admin pages.
     */
    public static function isAdminLoggedIn()
    {
        return self::isLoggedIn('admin') && self::isAdmin();
    }

    /**
     * isAdmin() = "is the person signed in to the admin panel an admin?"
     *
     * Reads the ADMIN area's role only, so being signed in as a normal
     * customer can never make this true.
     */
    public static function isAdmin()
    {
        return Session::get('admin_role') === 'admin';
    }

    /**
     * name($area) = "what is this person's name in this area?"
     *
     * Handy for the headers, which show "Hi, Ali" in the shop and the
     * admin's name in the panel.
     */
    public static function name($area = 'customer')
    {
        $prefix = $area === 'admin' ? 'admin_' : 'customer_';

        return Session::get($prefix . 'name');
    }

    /**
     * id($area) = "what is this person's user id in this area?"
     *
     * Used by the shop's pages for things like "only show THIS
     * customer's orders".
     */
    public static function id($area = 'customer')
    {
        $prefix = $area === 'admin' ? 'admin_' : 'customer_';

        return Session::get($prefix . 'id');
    }

    /**
     * requireLogin() = DOOR GUARD for shop pages that need a customer
     *
     * Put this at the TOP of any shop page that needs a signed-in
     * customer (checkout, account, order confirmation):
     *
     *      Auth::requireLogin('login.php');
     *
     * Signed in  -> nothing happens, the page carries on.
     * Not signed in -> sent to the login page, and the page stops.
     */
    public static function requireLogin($redirectTo = 'login.php')
    {
        if (self::isLoggedIn('customer')) {
            return;   // customer is signed in -- carry on
        }

        // --- no customer signed in, so bounce them to the shop login ---

        // Remember where they were heading so the login page can send
        // them back there afterwards instead of the homepage.
        $current = $_SERVER['REQUEST_URI'] ?? '';
        $separator = strpos($redirectTo, '?') === false ? '?' : '&';

        header('Location: ' . $redirectTo . $separator . 'redirect=' . urlencode($current));

        // exit; is NOT optional. header() only works while nothing has
        // been printed yet. Without exit, PHP would carry on and try
        // to print this page's HTML after the redirect, which breaks it.
        exit;
    }

    /**
     * requireAdmin() = DOOR GUARD for the admin panel
     *
     * Put this at the top of every file in admin/:
     *
     *      Auth::requireAdmin('../login.php');
     *
     * It only ever looks at the ADMIN keys, so a signed-in customer
     * (or a signed-in admin browsing the shop) cannot get in here.
     */
    public static function requireAdmin($redirectTo = '/admin/login.php')
    {
        if (self::isAdminLoggedIn()) {
            return;   // signed in to admin AND is an admin -- let them in
        }

        header('Location: ' . $redirectTo);
        exit;
    }
}
