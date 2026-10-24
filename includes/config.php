<?php
ob_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
session_start();

function h($value) {
	return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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
	$db = new PDO(
		"mysql:host=" . DBHOST . ";dbname=" . DBNAME . ";charset=utf8mb4",
		DBUSER,
		DBPASS,
		array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		)
	);

} catch(PDOException $e) {
	error_log($e->getMessage());
	http_response_code(500);
	exit('Database connection unavailable.');
}

//include the user class, pass in the database connection
include('classes/user.php');
include('classes/phpmailer/mail.php');
$user = new User($db);
$genpass= new Password();
?>
