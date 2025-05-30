<?php
$host = 'localhost'; /* your host address */
$db = 'db_name'; /* your db name */
$user = 'username'; /* your db user */
$pass = 'password;'; /* your db pass */

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
