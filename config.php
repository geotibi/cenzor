<?php
$host = 'localhost'; /* your host address */
$db = 'db_name'; /* your db name */
$user = 'username'; /* your db user */
$pass = 'password;'; /* your db pass */
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("DB Connection failed: " . $e->getMessage());
}
?>
