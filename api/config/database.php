<?php

class Database
{
    private static ?PDO $instance = null;

    private static string $host = 'localhost';
    private static string $dbname = 'twistpro_citaclick';
    private static string $username = 'twistpro_citaclickuser';
    private static string $password = '';
    private static string $charset = 'utf8mb4';

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $envPassword = getenv('DB_PASS');
            if ($envPassword !== false) {
                self::$password = $envPassword;
            }

            $envHost = getenv('DB_HOST');
            if ($envHost !== false) {
                self::$host = $envHost;
            }

            $dsn = 'mysql:host=' . self::$host
                 . ';dbname=' . self::$dbname
                 . ';charset=' . self::$charset;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            self::$instance = new PDO($dsn, self::$username, self::$password, $options);
        }

        return self::$instance;
    }

    public static function close(): void
    {
        self::$instance = null;
    }
}
