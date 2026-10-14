<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $availableDrivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
        $orderedDrivers = [];

        foreach (['pgsql', 'mysql', 'sqlite'] as $candidate) {
            if (in_array($candidate, $availableDrivers, true)) {
                $orderedDrivers[] = $candidate;
            }
        }

        $requestedDriver = DB_DRIVER;
        $driver = in_array($requestedDriver, $availableDrivers, true)
            ? $requestedDriver
            : ($orderedDrivers[0] ?? null);

        if ($driver === null) {
            $driverError = 'No supported PDO database driver is available. Install the PostgreSQL driver (pdo_pgsql) or configure a compatible MySQL/SQLite database.';
            error_log($driverError);
            if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
                die($driverError);
            }
            die('A database error occurred. Please try again later.');
        }

        try {
            switch ($driver) {
                case 'pgsql':
                    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
                    if (DB_SSLMODE !== '') {
                        $dsn .= ';sslmode=' . DB_SSLMODE;
                    }
                    $username = DB_USER;
                    $password = DB_PASS;
                    break;
                case 'mysql':
                    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                    $username = DB_USER;
                    $password = DB_PASS;
                    break;
                case 'sqlite':
                    $dsn = 'sqlite:' . DB_PATH;
                    $username = null;
                    $password = null;
                    break;
                default:
                    throw new RuntimeException('Unsupported database driver: ' . $driver);
            }

            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed for ' . DB_HOST . '/' . DB_NAME . ' using ' . $driver . ': ' . $e->getMessage());
            if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
                die("Database connection failed: " . $e->getMessage());
            }
            die("A database error occurred. Please try again later.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function insert($sql, $params = []) {
        if (stripos($sql, 'RETURNING') === false) {
            $sql .= ' RETURNING id';
        }
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch();
        return $row ? $row['id'] : null;
    }

    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
}
