<?php
//Create_connection credentials

$db_host = 'localhost'; 
$db_user = getenv("DB_USER") ?: "";
$db_pass = getenv("DB_PASSWORD") ?: "";
$db_name = 'test';
//Create mysqli object
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

//Error handler
if($mysqli->connect_error) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
    exit();
}
?>
