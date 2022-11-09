<?php

$db_host = 'localhost';
$db_name = 'phpauth';
$db_user = 'root';
$conn = new mysqli(
    $db_host,
    $db_user,
    null,
    $db_name
);

if ($conn->connect_error) {
    die('database connection error: ' . $conn->connect_error);
}
