<?php require('includes/config.php');

//if logged in redirect to members page
if( $user->is_logged_in() ){ header('Location: memberpage.php'); exit; }

//if form has been submitted process it
if(isset($_POST['submit'])){
	$username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
	$email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
	$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
	$passwordConfirm = is_string($_POST['passwordConfirm'] ?? null) ? $_POST['passwordConfirm'] : '';
	$institute = is_string($_POST['ins'] ?? null) ? $_POST['ins'] : '';
	$clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
	$canRegister = rate_limit_allow($db, 'activation-ip', $clientIp, 250, 3600);

	//very basic validation
	if(strlen($username) < 3){
		$error[] = 'Username is too short.';
	}

	if(strlen($password) < 3){
		$error[] = 'Password is too short.';
	}

	if(strlen($passwordConfirm) < 3){
		$error[] = 'Confirm password is too short.';
	}

	if($password !== $passwordConfirm){
		$error[] = 'Passwords do not match.';
	}
	
	if(strlen($institute) < 3){
		$error[] = 'Institute is too short.';
		
	}

	//email validation
	if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
	    $error[] = 'Please enter a valid email address';
	} else {
		if ($canRegister) {
			$canRegister = rate_limit_allow($db, 'activation-email', strtolower(trim($email)), 3, 3600);
		}
	}

	//if no errors have been created carry on
	if(!isset($error)){
		if (!$canRegister) {
			header('Location: registerpage.php?action=joined');
			exit;
		}

		try {
			$stmt = $db->prepare('SELECT memberID, username, email, active FROM members WHERE username = :username OR email = :email');
			$stmt->execute(array(':username' => $username, ':email' => $email));
			$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$inactiveAccount = null;

			foreach ($accounts as $account) {
				if (strcasecmp($account['username'], $username) === 0
					&& strcasecmp($account['email'], $email) === 0
					&& strcasecmp($account['active'], 'Yes') !== 0) {
					$inactiveAccount = $account;
					break;
				}
			}

			if ($accounts && (!$inactiveAccount || count($accounts) !== 1)) {
				header('Location: registerpage.php?action=joined');
				exit;
			}

			$activation = bin2hex(random_bytes(32));
			if ($inactiveAccount) {
				$id = $inactiveAccount['memberID'];
				$to = $inactiveAccount['email'];
				$stmt = $db->prepare("UPDATE members SET active = :active, activationExpiresAt = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE memberID = :member_id AND active <> 'Yes'");
				$stmt->execute(array(':active' => $activation, ':member_id' => $id));
				if ($stmt->rowCount() !== 1) {
					header('Location: registerpage.php?action=joined');
					exit;
				}
			} else {
				$hashedpassword = password_hash($password, PASSWORD_DEFAULT);
				if ($hashedpassword === false) {
					throw new RuntimeException('Password could not be hashed.');
				}
				$stmt = $db->prepare("INSERT INTO members (username,password,email,active,activationExpiresAt,ip,institute,lastsolvetime) VALUES (:username, :password, :email, :active, DATE_ADD(NOW(), INTERVAL 1 DAY), :ip, :inst, ADDTIME(NOW(), '5:30:00'))");
				$stmt->execute(array(
					':username' => $username,
					':password' => $hashedpassword,
					':email' => $email,
					':active' => $activation,
					':ip' => $clientIp,
					':inst' => $institute,
				));
				$id = $db->lastInsertId();
				if ($id === false || $id === '0') {
					throw new RuntimeException('A member ID was not generated.');
				}
				$to = $email;
			}

			$activationUrl = h(DIR . 'activate.php?x=' . rawurlencode((string) $id) . '&y=' . rawurlencode($activation));
			$body = "<p>Thank you for registering for " . h(SITENAME) . ".</p>
			<p>To activate your account, please click on this link: <a href='" . $activationUrl . "'>" . $activationUrl . "</a></p>";

			$mail = new Mail();
			$mail->setFrom(SITEEMAIL);
			$mail->addAddress($to);
			$mail->subject('Registration Confirmation');
			$mail->body($body);
			if (!$mail->send()) {
				http_response_code(503);
				$error[] = 'We could not send the activation email. Please try again later.';
			} else {
				header('Location: registerpage.php?action=joined');
				exit;
			}

		} catch (Throwable $e) {
			error_log($e->getMessage());
			http_response_code(500);
			$error[] = 'We could not process the request. Please try again later.';
		}

	}

}

//define page title
$title = SITENAME . ' | Register';

//include header template
require('layout/header.php');
?>


<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
			<form role="form" method="post" action="" autocomplete="off">
				<?= csrf_field() ?>
				<h2>Please Sign Up</h2>
				<p>Already a member? <a href='login.php'>Login</a></p>
				<hr>

				<?php
				//check for any errors
				if(isset($error)){
					foreach($error as $error){
						echo '<p class="alert-danger">'.h($error).'</p>';
					}
				}

				//if action is joined show sucess
				if(isset($_GET['action']) && $_GET['action'] == 'joined'){
					echo "<div class='alert alert-success'>If registration can be completed with those details, you will receive an activation link.</div>";
				}
				if(isset($_GET['action']) && $_GET['action'] == 'deleted'){
					echo "<div class='alert alert-success'>Your account and answer history have been deleted.</div>";
				}
				?>

				<div class="form-group">
					<input type="text" name="username" id="username" class="form-control input-lg" placeholder="User Name" value="<?php if(isset($error)){ echo h($_POST['username'] ?? ''); } ?>" tabindex="1">
				</div>
				<div class="form-group">
					<input type="email" name="email" id="email" class="form-control input-lg" placeholder="Email Address" value="<?php if(isset($error)){ echo h($_POST['email'] ?? ''); } ?>" tabindex="2">
				</div>
				<div class="form-group">
					<input type="text" name="ins" id="ins" class="form-control input-lg" placeholder="Institution"  tabindex="3">
				</div>
				<div class="row">
					<div class="col-xs-6 col-sm-6 col-md-6">
						<div class="form-group">
							<input type="password" name="password" id="password" class="form-control input-lg" placeholder="Password" tabindex="4">
						</div>
					</div>
					<div class="col-xs-6 col-sm-6 col-md-6">
						<div class="form-group">
							<input type="password" name="passwordConfirm" id="passwordConfirm" class="form-control input-lg" placeholder="Confirm Password" tabindex="5">
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-xs-6 col-md-6"><input type="submit" name="submit" value="Register" class="btn btn-primary btn-block btn-lg" tabindex="6"></div>
				</div>
			</form>
			<h3>General Instructions</h3>
			<ul>
			    <li>Do <b>NOT</b> use special characters, spaces, etc. in your username. Please use only letters and numbers.</li>
			    <li>After sign up you will receive a verification link on the  email registered. Verify by clicking on the link and then login. <u>You won't be able to login unless you have verified your account.</u></li>
			    <li>Also, try to use a desktop or laptops as much as possible. Some levels might require it.</li>
			</ul>
		</div>
	</div>

</div>

<?php
//include header template
require('layout/footer.php');
?>
