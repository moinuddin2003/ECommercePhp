if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Return MySQL database credentials
return [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',             // Leave empty for default XAMPP/Laragon, or add your password
    'dbname' => 'ecommerce_db',  // Make sure this matches the database name you created in MySQL
    'port' => 3306,
    'charset' => 'utf8mb4'
];