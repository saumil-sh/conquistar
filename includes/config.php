<?php
ob_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
session_start();

//set timezone
date_default_timezone_set('Europe/Germany');

//database credentials
define('DBHOST','localhost');
define("DBUSER", getenv("DB_USER") ?: "");
define("DBPASS", getenv("DB_PASSWORD") ?: "");
define('DBNAME','test');

//application address
define('DIR','http://localhost:8000/'); //May need modification
define('SITEEMAIL','samdon97@gmail.com');

try {

	//create PDO connection
	$db = new PDO("mysql:host=".DBHOST.";dbname=".DBNAME, DBUSER, DBPASS);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
	//show error
    echo '<p class="bg-danger">'.$e->getMessage().'</p>';
    exit;
}

//include the user class, pass in the database connection
include('classes/user.php');
include('classes/phpmailer/mail.php');
$user = new User($db);
$genpass= new Password();
?>

