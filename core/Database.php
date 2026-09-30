<?php
/**
 * core/Database.php  --  PLAIN ENGLISH VERSION
 * ===================================================================
 * THE ONE IDEA IN THIS FILE
 * -------------------------------------------------------------------
 * Your data lives in MySQL. PHP cannot read MySQL data directly.
 * To get data out, PHP must always do the SAME 4 steps:
 *
 *      1. PREPARE  -> "here is the question I want to ask"
 *      2. BIND     -> "here are the values for the blanks"
 *      3. EXECUTE  -> "go and ask it"
 *      4. READ     -> "give me the answer"
 *
 * Those 4 steps need 4 lines of code. And you have ~40 pages in this
 * project that all need queries. So instead of writing those 4 lines
 * 300 times, this class hides them inside ONE method called run().
 *
 * You then get 4 short methods that wrap run() for you:
 *
 *      $db->fetchAll(...)   SELECT, many rows
 *      $db->fetchOne(...)   SELECT, one row
 *      $db->insert(...)     INSERT, gives back the new id
 *      $db->execute(...)    UPDATE / DELETE, gives back rows changed
 *
 * ===================================================================
 * THE SECOND IDEA: THE "?" HOLES  (read this bit twice)
 * ===================================================================
 * A normal SQL question looks like this:
 *
 *      SELECT * FROM users WHERE email = 'ali@example.com'
 *                          ^^^^^^^^^^^^^^^^^^^^^^^^^
 *                          the value is BAKED INTO the text
 *
 * That is bad, because the email changes depending on who is typing.
 * So instead of baking the value in, we leave a HOLE:
 *
 *      SELECT * FROM users WHERE email = ?
 *                                    ^ a hole
 *
 * And we hand the value over SEPARATELY, in an array:
 *
 *      $db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
 *                        ^ the question with holes     ^ the answers
 *
 * Why bother? Because MySQL can now tell the difference between
 * a question and an answer. If a visitor types
 *
 *      ali@example.com' OR '1'='1
 *
 * ...MySQL is not fooled. It just looks for a user whose email is
 * LITERALLY that weird string, finds nobody, and returns nothing.
 *
 *      THE GOLDEN RULE:  values go in the array.
 *                         NEVER build them into the SQL text.
 *
 * ===================================================================
 * ABOUT THE 3RD PARAMETER ($types) -- YOU CAN IGNORE IT
 * ===================================================================
 * Real mysqli wants to know the type of each value:
 *
 *      s = String      i = Integer      d = Decimal
 *
 * so old code looks scary like this:
 *
 *      $db->fetchOne('SELECT * FROM users WHERE id = ?', [$id], 'i');
 *                                                  ^^^^^  'i' = integer
 *
 * This version works that out FOR YOU by looking at the value.
 * If you leave the 3rd parameter off, it is still correct.
 * (The older pages in this project still pass it, and that still works
 *  exactly the same -- so nothing breaks.)
 * ===================================================================
 */

class Database
{
    /**
     * A "property" is just a variable that belongs to the class.
     * "private" means: only code INSIDE this class may use it.
     * It holds the MySQL connection that config/database.php created.
     */
    private $conn;

    /**
     * __construct = "what happens when someone creates this thing".
     * It runs by itself the moment a page writes:  new Database($conn)
     *
     * All it does is keep hold of the connection so we can reuse it.
     */
    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * run() = THE ENGINE. It does steps 1, 2 and 3 above.
     * The other 4 methods all call this one, so the 4-step dance
     * only has to be understood ONCE.
     *
     * @param string $sql    your question, with ? holes
     * @param array  $params the answers for those holes
     * @return object        the mysqli statement, used to read the answer
     */
    public function run($sql, $params = [], $types = '')
    {
        // ---------- STEP 1: PREPARE ----------
        // Hand the question to MySQL. The values are NOT sent yet.
        $stmt = mysqli_prepare($this->conn, $sql);

        if ($stmt === false) {
            // It failed, which almost always means a typo in the SQL
            // (e.g. a column that does not exist). Show why, then stop.
            // die() prints the message and ends the whole page.
            die('SQL problem: ' . mysqli_error($this->conn) . ' | SQL was: ' . $sql);
        }

        // ---------- STEP 2: BIND ----------
        // Only needed if there ARE values to put in the holes.
        if (count($params) > 0) {

            // If the caller did not say what type each value is,
            // look at the values ourselves and work it out.
            if ($types === '') {
                $types = $this->guessTypes($params);
            }

            // mysqli is old-fashioned: it wants each value as its own
            // argument, not as an array. The "..." (called "spread")
            // unpacks our array so it arrives as separate arguments.
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        // ---------- STEP 3: EXECUTE ----------
        // Now it actually runs.
        mysqli_stmt_execute($stmt);

        // ---------- STEP 4 (part 1): HAND IT BACK ----------
        // The caller still needs this object to read the answer,
        // so we return it instead of throwing it away.
        return $stmt;
    }

    /**
     * Works out the type letters so you don't have to.
     *
     *   whole number (int)   ->  i
     *   number with a point  ->  d
     *   anything else        ->  s   (text, dates, NULL, true/false...)
     *
     * One letter is produced per value, in the same order.
     */
    private function guessTypes($params)
    {
        $types = '';

        foreach ($params as $value) {
            if (is_int($value) || is_bool($value)) {
                $types .= 'i';   // whole number
            } elseif (is_float($value)) {
                $types .= 'd';   // number with a decimal point
            } else {
                $types .= 's';   // text
            }
        }

        return $types;
    }

    /**
     * LEARNING TOOL -- not needed by the website at all.
     * It shows you the finished question with the answers filled in,
     * so you can SEE what MySQL is really being asked.
     *
     *   echo Database::showSql('SELECT * FROM users WHERE email = ?', ['ali@example.com']);
     *   // prints: SELECT * FROM users WHERE email = 'ali@example.com'
     *
     * Very handy when a query returns nothing and you are wondering why.
     */
    public static function showSql($sql, $params = [])
    {
        if (count($params) === 0) {
            return $sql;
        }

        // Step 1: put a recognisable marker in every hole.
        $marker = "'__HOLE__'";
        $out = str_replace('?', $marker, $sql);

        // Step 2: replace one marker at a time with the real value.
        foreach ($params as $value) {
            if (is_bool($value)) {
                $text = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $text = (string) $value;
            } else {
                $text = "'" . addslashes($value) . "'";
            }

            $position = strpos($out, $marker);
            if ($position === false) {
                break;   // ran out of markers -- the counts did not match
            }
            $out = substr_replace($out, $text, $position, strlen($marker));
        }

        return $out;
    }

    /* ==================================================================
       THE 4 METHODS YOU WILL ACTUALLY USE EVERY DAY
       ================================================================== */

    /**
     * fetchAll() = "give me MANY rows"
     *
     * Returns: an array of rows. If nothing matched, you get an empty
     *          array [] -- which is perfect for foreach (it just runs
     *          zero times, no error).
     *
     *   $products = $db->fetchAll('SELECT * FROM products WHERE category_id = ?', [$id]);
     *
     *   foreach ($products as $product) {
     *       echo $product['name'];    // MYSQLI_ASSOC gives us column names
     *   }
     */
    public function fetchAll($sql, $params = [], $types = '')
    {
        $stmt = $this->run($sql, $params, $types);          // steps 1-3

        $result = mysqli_stmt_get_result($stmt);            // step 4a: get the table
        $rows   = mysqli_fetch_all($result, MYSQLI_ASSOC);  // step 4b: table -> PHP arrays

        mysqli_stmt_close($stmt);                           // tidy up memory

        return $rows;
    }

    /**
     * fetchOne() = "give me ONE row"
     *
     * Returns: that one row as an array,
     *          or null if nothing was found.
     *
     *   $user = $db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
     *
     *   if ($user === null) {
     *       echo 'No such email';
     *   } else {
     *       echo $user['name'];
     *   }
     *
     * MYSQLI_ASSOC above turns each row into ['id' => 1, 'name' => 'Ali']
     * instead of [0 => 1, 1 => 'Ali']. That is why we can say $user['name'].
     */
    public function fetchOne($sql, $params = [], $types = '')
    {
        $rows = $this->fetchAll($sql, $params, $types);

        // [0] means "item number 0" = the first row.
        // ?? means "if there is no first row, hand back null instead of crashing".
        // (?? is short for "if not set, use this default".)
        return $rows[0] ?? null;
    }

    /**
     * insert() = "add a new row"
     *
     * Returns: the id MySQL gave the new row.
     *          (Every table has an AUTO_INCREMENT column that counts up:
     *           1, 2, 3, 4... and that new number is what you get back.)
     *
     *   $newId = $db->insert(
     *       'INSERT INTO categories (name, slug) VALUES (?, ?)',
     *       [$name, $slug]
     *   );
     *   echo "Saved as category number $newId";
     *
     * Why you want that id: often the next thing you do needs it,
     * e.g. saving the order_items rows for that new order.
     */
    public function insert($sql, $params = [], $types = '')
    {
        $stmt = $this->run($sql, $params, $types);
        mysqli_stmt_close($stmt);

        // Ask the connection: "what number did you just use?"
        return mysqli_insert_id($this->conn);
    }

    /**
     * execute() = "change or remove rows"
     *
     * Returns: how many rows were changed.
     *
     *   $changed = $db->execute('UPDATE products SET stock = ? WHERE id = ?', [50, 7]);
     *   echo "$changed products updated";
     *
     * Useful for messages like "3 products updated" or "Nothing matched".
     */
    public function execute($sql, $params = [], $types = '')
    {
        $stmt = $this->run($sql, $params, $types);

        $changed = mysqli_stmt_affected_rows($stmt);

        mysqli_stmt_close($stmt);

        return $changed;
    }
}
