<?php
/**
 * core/Database.php
 * ------------------------------------------------
 * A small wrapper around mysqli so every page can run
 * queries in ONE line, instead of repeating
 * prepare/bind/execute every time.
 *
 * IMPORTANT: This still uses prepared statements behind
 * the scenes. Never build SQL by concatenating $_GET or
 * $_POST values directly into a query string — always
 * pass them through $params here instead. That's what
 * stops SQL injection.
 *
 * USAGE EXAMPLES (once you have $db = new Database($conn);):
 *
 *   // SELECT multiple rows
 *   $products = $db->fetchAll(
 *       "SELECT * FROM products WHERE category_id = ?",
 *       [$categoryId],
 *       'i'   // i = integer, s = string, d = double
 *   );
 *
 *   // SELECT a single row
 *   $user = $db->fetchOne(
 *       "SELECT * FROM users WHERE email = ?",
 *       [$email],
 *       's'
 *   );
 *
 *   // INSERT (returns the new row's id)
 *   $newId = $db->insert(
 *       "INSERT INTO categories (name, slug) VALUES (?, ?)",
 *       [$name, $slug],
 *       'ss'
 *   );
 *
 *   // UPDATE / DELETE
 *   $db->execute(
 *       "UPDATE products SET stock = ? WHERE id = ?",
 *       [$newStock, $productId],
 *       'ii'
 *   );
 */

class Database
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * Prepares and executes a query, binding params if given.
     * Returns the mysqli_stmt so callers can pull results/info from it.
     */
    private function run($sql, $params = [], $types = '')
    {
        $stmt = mysqli_prepare($this->conn, $sql);

        if (!$stmt) {
            die('Query prepare failed: ' . mysqli_error($this->conn));
        }

        if (!empty($params)) {
            // mysqli_stmt_bind_param needs the args passed one by one,
            // not as an array — the "..." spreads the array out for us.
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);

        return $stmt;
    }

    /** Run a SELECT and return ALL matching rows as an array of assoc arrays. */
    public function fetchAll($sql, $params = [], $types = '')
    {
        $stmt = $this->run($sql, $params, $types);
        $result = mysqli_stmt_get_result($stmt);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $rows;
    }

    /** Run a SELECT and return just the FIRST matching row (or null if none). */
    public function fetchOne($sql, $params = [], $types = '')
    {
        $rows = $this->fetchAll($sql, $params, $types);

        return $rows[0] ?? null;
    }

    /** Run an INSERT and return the new row's auto-increment id. */
    public function insert($sql, $params = [], $types = '')
    {
        $stmt = $this->run($sql, $params, $types);
        mysqli_stmt_close($stmt);

        return mysqli_insert_id($this->conn);
    }

    /** Run an UPDATE / DELETE. Returns how many rows were affected. */
    public function execute($sql, $params = [], $types = '')
    {
        $stmt = $this->run($sql, $params, $types);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return $affected;
    }
}