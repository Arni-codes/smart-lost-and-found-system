<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Reusable PDO Database Connection Class
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static ?string $lastError = null;

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern).
     */
    private function __construct() {}

    /**
     * Get the active PDO database connection instance.
     *
     * @return PDO
     * @throws PDOException if connection fails
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
            $port = defined('DB_PORT') ? DB_PORT : 3306;
            $db   = defined('DB_NAME') ? DB_NAME : 'smart_lost_and_found';
            $user = defined('DB_USER') ? DB_USER : 'root';
            $pass = defined('DB_PASS') ? DB_PASS : '';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
                self::$lastError = null;
            } catch (PDOException $e) {
                self::$lastError = $e->getMessage();
                // Never expose passwords or raw DSN credentials in public outputs
                error_log("Database Connection Error: " . $e->getMessage());
                throw new PDOException("Database connection failed. Please ensure MySQL is running in XAMPP and the database exists.", (int)$e->getCode());
            }
        }

        return self::$instance;
    }

    /**
     * Test the database connection safely without throwing uncaught exceptions.
     *
     * @return array [ 'connected' => bool, 'message' => string, 'details' => array ]
     */
    public static function testConnection(): array {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? DB_PORT : 3306;
        $db   = defined('DB_NAME') ? DB_NAME : 'smart_lost_and_found';
        $user = defined('DB_USER') ? DB_USER : 'root';

        try {
            $pdo = self::getConnection();
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            return [
                'connected' => true,
                'message'   => 'Connected successfully to MySQL server.',
                'details'   => [
                    'host'           => $host,
                    'port'           => $port,
                    'database'       => $db,
                    'user'           => $user,
                    'server_version' => $serverVersion,
                    'driver'         => $driverName
                ]
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'message'   => $e->getMessage(),
                'details'   => [
                    'host'     => $host,
                    'port'     => $port,
                    'database' => $db,
                    'user'     => $user,
                    'hint'     => 'Check that MySQL is started in XAMPP Control Panel and database/schema.sql has been imported.'
                ]
            ];
        }
    }

    /**
     * Reset the cached instance (useful during testing or reconnections).
     */
    public static function reset(): void {
        self::$instance = null;
        self::$lastError = null;
    }
}

/**
 * Functional convenience wrapper to retrieve the PDO database connection.
 *
 * @return PDO
 */
function get_db_connection(): PDO {
    return Database::getConnection();
}
