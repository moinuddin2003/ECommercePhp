<?php
/**
 * config/database.php
 * ------------------------------------------------
 * Holds the database connection details and creates
 * a single mysqli connection ($conn) that the rest
 * of the app will reuse.
 *
 * Change these 4 values to match your local setup
 * (XAMPP/WAMP/Laragon default user is usually "root"
 * with an empty password).
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'root');
define('DB_PASS', 'moin123');

// Create the connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Stop everything if the connection failed — no point continuing
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Force utf8mb4 so emojis / special characters don't break
mysqli_set_charset($conn, 'utf8mb4');