<?php
//include config
require_once('includes/config.php');

//check if already logged in move to home page
if( $user->is_logged_in() ){ header('Location: memberpage.php'); exit; }
//process login form if submitted
if(isset($_POST['submit'])){

	$username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
	$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
	$clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
	$canLogin = rate_limit_allow($db, 'login-ip', $clientIp, 300, 900);
	if ($canLogin) {
		$canLogin = rate_limit_allow($db, 'login-user-ip', strtolower(trim($username)) . "\0" . $clientIp, 10, 900);
	}

	if($canLogin && $user->login($username,$password)){
		header('Location: memberpage.php');
		exit;
	
	} else {
		$error[] = 'Unable to sign in. Check your username and password.';
	}

}//end if submit

//define page title
$title = 'Login';

//include header template
require('layout/header.php'); 
?>
<main class="container">
<form method="post" action="" autocomplete="off">
	<?= csrf_field() ?>
	<h2>Please Login</h2>
	<p><a href="./">Back to home page</a></p>
	<?php if (isset($error)): foreach ($error as $message): ?>
	<p class="alert alert-danger"><?= h($message) ?></p>
	<?php endforeach; endif; ?>
	<?php
	$messages = array(
		'active' => 'Your account is now active you may now log in.',
		'reset' => 'If an account matches, a reset link will be sent.',
		'resetAccount' => 'Password changed, you may now login.',
	);
	$action = $_GET['action'] ?? '';
	if (is_string($action) && isset($messages[$action])): ?>
	<p class="alert alert-success"><?= h($messages[$action]) ?></p>
	<?php endif; ?>
	<input type="text" name="username" id="username" placeholder="User Name" aria-label="User Name" value="<?php if (isset($error)) { echo h($_POST['username'] ?? ''); } ?>">
	<input type="password" name="password" id="password" placeholder="Password" aria-label="Password">
	<p><a href="reset.php">Forgot Password</a></p>
	<input type="submit" name="submit" value="Login">
</form>
</main>

<?php 
//include header template
require('layout/footer.php'); 
?>
