<?php require('includes/config.php'); 

//if logged in redirect to members page
if( $user->is_logged_in() ){ header('Location: memberpage.php'); exit; }

$key = is_string($_GET['key'] ?? null) ? $_GET['key'] : '';
$stmt = $db->prepare("SELECT memberID, resetToken FROM members WHERE resetToken = :token AND resetComplete = 'No' AND resetExpiresAt > NOW()");
$stmt->execute(array(':token' => $key));
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($row['resetToken'])) {
	$stop = 'This password reset link is invalid, expired, or has already been used.';
}

//if form has been submitted process it
if(!isset($stop) && isset($_POST['submit'])){
	$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
	$passwordConfirm = is_string($_POST['passwordConfirm'] ?? null) ? $_POST['passwordConfirm'] : '';

	//basic validation
	if(strlen($password) < 3){
		$error[] = 'Password is too short.';
	}

	if(strlen($passwordConfirm) < 3){
		$error[] = 'Confirm password is too short.';
	}

	if($password !== $passwordConfirm){
		$error[] = 'Passwords do not match.';
	}

	//if no errors have been created carry on
	if(!isset($error)){

		//hash the password
		$hashedpassword = password_hash($password, PASSWORD_DEFAULT);
		if ($hashedpassword === false) {
			throw new RuntimeException('Password could not be hashed.');
		}

		try {

			$stmt = $db->prepare("UPDATE members SET password = :hashedpassword, resetToken = '', resetComplete = 'Yes', resetExpiresAt = NULL WHERE memberID = :member_id AND resetToken = :token AND resetComplete = 'No' AND resetExpiresAt > NOW()");
			$stmt->execute(array(
				':hashedpassword' => $hashedpassword,
				':member_id' => $row['memberID'],
				':token' => $row['resetToken']
			));

			if ($stmt->rowCount() === 1) {
				header('Location: login.php?action=resetAccount');
				exit;
			}

			$stop = 'This password reset link is invalid or has already been used.';

		//else catch the exception and show the error.
		} catch(Throwable $e) {
			error_log($e->getMessage());
			http_response_code(500);
		    $error[] = 'We could not update the password. Please try again later.';
		}

	}

}

//define page title
$title = 'Reset Account';

//include header template
require('layout/header.php'); 
?>

<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">


	    	<?php if(isset($stop)){

				echo "<p class='alert-danger'>".h($stop)."</p>";

	    	} else { ?>

				<form role="form" method="post" action="" autocomplete="off">
					<?= csrf_field() ?>
					<h2>Change Password</h2>
					<hr>

					<?php
					//check for any errors
					if(isset($error)){
						foreach($error as $error){
							echo '<p class="alert-danger">'.h($error).'</p>';
						}
					}

					//check the action
					switch ($_GET['action']) {
						case 'active':
							echo "<h2 class='alert-success'>Your account is now active you may now log in.</h2>";
							break;
						case 'reset':
							echo "<h2 class='alert-success'>Please check your inbox for a reset link.</h2>";
							break;
					}
					?>

					<div class="row">
						<div class="col-xs-6 col-sm-6 col-md-6">
							<div class="form-group">
								<input type="password" name="password" id="password" class="form-control input-lg" placeholder="Password" tabindex="1">
							</div>
						</div>
						<div class="col-xs-6 col-sm-6 col-md-6">
							<div class="form-group">
								<input type="password" name="passwordConfirm" id="passwordConfirm" class="form-control input-lg" placeholder="Confirm Password" tabindex="1">
							</div>
						</div>
					</div>
					
					<hr>
					<div class="row">
						<div class="col-xs-6 col-md-6"><input type="submit" name="submit" value="Change Password" class="btn btn-primary btn-block btn-lg" tabindex="3"></div>
					</div>
				</form>

			<?php } ?>
		</div>
	</div>


</div>

<?php 
//include header template
require('layout/footer.php'); 
?>