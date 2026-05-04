<?php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST');
        $db   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE');
        $user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER');
        $pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD');
        $port = getenv('MYSQLPORT') ?: getenv('MYSQSL_PORT');
        if (!$port) $port = '3306';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    }
    return $pdo;
}
