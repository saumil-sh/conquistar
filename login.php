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
		$stmt = $db->prepare('UPDATE members SET logincount = logincount + 1 WHERE username = :username');
		$stmt->execute(array(':username' => $username));
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

	
<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
			<form role="form" method="post" action="" autocomplete="off">
				<?= csrf_field() ?>
				<h2>Please Login</h2>
				<p><a href='./'>Back to home page</a></p>
				<hr>

				<?php
				//check for any errors
				if(isset($error)){
					foreach($error as $error){
						echo '<p class="alert-danger">'.h($error).'</p>';
					}
				}

				if(isset($_GET['action'])){

					//check the action
					switch ($_GET['action']) {
						case 'active':
							echo "<h2 class='alert-success'>Your account is now active you may now log in.</h2>";
							break;
						case 'reset':
							echo "<h2 class='alert-success'>If an account matches, a reset link will be sent.</h2>";
							break;
						case 'resetAccount':
							echo "<h2 class='alert-success'>Password changed, you may now login.</h2>";
							break;
					}

				}

				
				?>

				<div class="form-group">
					<input type="text" name="username" id="username" class="form-control input-lg" placeholder="User Name" value="<?php if(isset($error)){ echo h($_POST['username'] ?? ''); } ?>" tabindex="1">
				</div>
				

				<div class="form-group">
					<input type="password" name="password" id="password" class="form-control input-lg" placeholder="Password" tabindex="3">
				</div>
				<!--
				<div class="row">
					<div class="col-xs-9 col-sm-9 col-md-9">
						 <a href='reset.php'>Forgot your Password?</a>
					</div>
				</div>
				-->
				<p><a href="reset.php">Forgot Password</a></p>
				<div class="row">
					<div class="col-xs-6 col-md-6"><input type="submit" name="submit" value="Login" class="btn btn-primary btn-block btn-lg" tabindex="5"></div>
				</div>
			</form>
			
		</div>
	</div>



</div>


<?php 
//include header template
require('layout/footer.php'); 
?>
