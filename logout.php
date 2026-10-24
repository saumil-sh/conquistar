<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	header('Allow: POST');
	http_response_code(405);
	exit('Method not allowed.');
}

require_once('includes/config.php');
if (!$user->is_logged_in()) {
	header('Location: login.php');
	exit;
}

$stmt = $db->prepare('UPDATE members SET logincount = logincount - 1 WHERE username = :username');
$stmt->execute(array(':username' => $_SESSION['username']));
$user->logout();
//logged in return to index page
header('Location: registerpage.php');
exit;
?>