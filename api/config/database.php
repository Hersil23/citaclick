<?php

class Database
{
    private static ?PDO $instance = null;

    private static string $charset = 'utf8mb4';

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME');
            $username = getenv('DB_USER');
            $password = getenv('DB_PASS');

            if (!$dbname || !$username) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Server configuration error']);
                exit;
            }

            $dsn = 'mysql:host=' . $host
                 . ';dbname=' . $dbname
                 . ';charset=' . self::$charset;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            self::$instance = new PDO($dsn, $username, $password, $options);
        }

        return self::$instance;
    }

    public static function close(): void
    {
        self::$instance = null;
    }
}
