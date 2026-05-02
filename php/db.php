<?php
// db.php  –  Database connection (PDO)

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('MYSQLHOST');
        $db = getenv('MYSQLDATABASE');
        $user = getenv('MYSQLUSER');
        $pass = getenv('MYSQLPASSWORD');
        $port = getenv('MYSQLPORT') ?: '3306'
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4"
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}
