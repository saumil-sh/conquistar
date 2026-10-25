<?php
ob_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_set_cookie_params(array(
	'lifetime' => 0,
	'path' => '/',
	'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	'httponly' => true,
	'samesite' => 'Lax',
));
session_start();

function h($value) {
	return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_field() {
	return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

function csrf_is_valid() {
	return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
		&& is_string($_SESSION['csrf_token'])
		&& is_string($_POST['csrf_token'])
		&& hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

$csrfSessionMissing = !isset($_SESSION['csrf_token']);
if ($csrfSessionMissing) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrf_is_valid()) {
	http_response_code(400);
	if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'process.php' && empty($_SESSION['loggedin']) && ($csrfSessionMissing || !isset($_POST['csrf_token']))) {
		exit('Your quiz session expired. Please <a href="login.php">log in</a> and reopen the quiz before submitting again.');
	}
	if ($csrfSessionMissing || !isset($_POST['csrf_token'])) {
		exit('Your session or form token expired. Go back, reload the form, and try again.');
	}
	exit('This form could not be verified. Go back, reload the form, and try again.');
}

//set timezone
date_default_timezone_set('Europe/Germany');

//database credentials
define('DBHOST','localhost');
define("DBUSER", getenv("DB_USER") ?: "");
define("DBPASS", getenv("DB_PASSWORD") ?: "");
define('DBNAME','test');

// Email links use this configured origin, never the request Host header.
$appUrl = getenv('APP_URL');
if ($appUrl === false || $appUrl === '') {
	$appUrl = 'http://localhost:8000/';
}
$appUrlParts = parse_url($appUrl);
if (!filter_var($appUrl, FILTER_VALIDATE_URL)
	|| !is_array($appUrlParts)
	|| !isset($appUrlParts['scheme'], $appUrlParts['host'])
	|| $appUrlParts['host'] === ''
	|| !in_array(strtolower($appUrlParts['scheme']), array('http', 'https'), true)
	|| isset($appUrlParts['user'])
	|| isset($appUrlParts['pass'])
	|| isset($appUrlParts['query'])
	|| isset($appUrlParts['fragment'])) {
	http_response_code(500);
	exit('Application URL configuration is invalid.');
}
define('DIR', rtrim($appUrl, '/') . '/');
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

function rate_limit_allow(PDO $db, $bucket, $subject, $limit, $windowSeconds) {
	$now = time();
	$key = hash('sha256', $bucket . "\0" . $subject);
	$db->beginTransaction();

	try {
		$cleanup = $db->prepare('DELETE FROM request_limits WHERE window_started_at < :expired_before LIMIT 100');
		$cleanup->execute(array(':expired_before' => $now - 86400));

		$insert = $db->prepare('INSERT IGNORE INTO request_limits (rate_key, window_started_at, request_count) VALUES (:key, :started, 0)');
		$insert->execute(array(':key' => $key, ':started' => $now));

		$select = $db->prepare('SELECT window_started_at, request_count FROM request_limits WHERE rate_key = :key FOR UPDATE');
		$select->execute(array(':key' => $key));
		$row = $select->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new RuntimeException('Rate limit state could not be loaded.');
		}

		$resetWindow = (int) $row['window_started_at'] <= $now - $windowSeconds;
		$started = $resetWindow ? $now : (int) $row['window_started_at'];
		$count = $resetWindow ? 1 : min((int) $row['request_count'] + 1, $limit + 1);
		$update = $db->prepare('UPDATE request_limits SET window_started_at = :started, request_count = :count WHERE rate_key = :key');
		$update->execute(array(':started' => $started, ':count' => $count, ':key' => $key));

		$db->commit();
		return $count <= $limit;
	} catch (Throwable $e) {
		if ($db->inTransaction()) {
			$db->rollBack();
		}
		throw $e;
	}
}

//include the user class, pass in the database connection
include('classes/user.php');
include('classes/phpmailer/mail.php');
$user = new User($db);
$genpass= new Password();
?>
