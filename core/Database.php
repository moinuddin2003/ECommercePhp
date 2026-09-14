<?php

class Database
{
    private $connection;

    public function __construct()
    {
        // Connection error par warning ki jagah Exception throw karega
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // Configuration load karein
        $config = require __DIR__ . '/../config/database.php';

        try {
            $this->connection = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['dbname'],
                $config['port']
            );

            // Special characters aur emojis ke liye charset set karein
            $this->connection->set_charset($config['charset']);

        } catch (mysqli_sql_exception $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    /**
     * Prepared statements execution method
     * 
     * @param string $sql SQL Query
     * @param array $params Query Parameters
     * @param string $types Parameter types e.g., 'ssi' (string, string, int)
     * @return mysqli_stmt|mysqli_result
     */
    public function query($sql, $params = [], $types = '')
    {
        $stmt = $this->connection->prepare($sql);

        if (!empty($params)) {
            // Agar types pass nahi kiye toh automatically determine karle
            if (empty($types)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
            }

            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        // SELECT query ke liye result object return karega
        $result = $stmt->get_result();

        // INSERT/UPDATE/DELETE ke liye statement object return karega
        return $result !== false ? $result : $stmt;
    }

    // Last inserted ID get karne ke liye helper
    public function getLastInsertId()
    {
        return $this->connection->insert_id;
    }

    // Transactions setup (Orders checkout ke waqt kaam ayenge)
    public function beginTransaction()
    {
        return $this->connection->begin_transaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollback();
    }
}

?>