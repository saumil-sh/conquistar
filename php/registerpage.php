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
	if(!matches_rule($username, USERNAME_PATTERN)){
		$error[] = USERNAME_RULE;
	}

	if(!matches_rule($password, PASSWORD_PATTERN)){
		$error[] = PASSWORD_RULE;
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
	}

	//if no errors have been created carry on
	if(!isset($error)){
		// Only complete forms count against the per-email limit.
		if ($canRegister) {
			$canRegister = rate_limit_allow($db, 'activation-email', strtolower(trim($email)), 3, 3600);
		}
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
				$stmt = $db->prepare("INSERT INTO members (username,password,email,active,activationExpiresAt,ip,institute,lastsolvetime) VALUES (:username, :password, :email, :active, DATE_ADD(NOW(), INTERVAL 1 DAY), :ip, :inst, UTC_TIMESTAMP(6))");
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
<main class="container">
<form method="post" action="" autocomplete="off">
	<?= csrf_field() ?>
	<h2>Please Sign Up</h2>
	<p>Already a member? <a href="login.php">Login</a></p>
	<?php if (isset($error)): foreach ($error as $message): ?>
	<p class="alert alert-danger"><?= h($message) ?></p>
	<?php endforeach; endif; ?>
	<?php if (($_GET['action'] ?? '') === 'joined'): ?>
	<p class="alert alert-success">If registration can be completed with those details, you will receive an activation link.</p>
	<?php elseif (($_GET['action'] ?? '') === 'deleted'): ?>
	<p class="alert alert-success">Your account and answer history have been deleted.</p>
	<?php endif; ?>
	<input type="text" name="username" id="username" placeholder="User Name" aria-label="User Name" value="<?php if (isset($error)) { echo h($_POST['username'] ?? ''); } ?>">
	<input type="email" name="email" id="email" placeholder="Email Address" aria-label="Email Address" value="<?php if (isset($error)) { echo h($_POST['email'] ?? ''); } ?>">
	<input type="text" name="ins" id="ins" placeholder="Institution" aria-label="Institution">
	<input type="password" name="password" id="password" placeholder="Password" aria-label="Password">
	<input type="password" name="passwordConfirm" id="passwordConfirm" placeholder="Confirm Password" aria-label="Confirm Password">
	<input type="submit" name="submit" value="Register">
</form>
<h3>General Instructions</h3>
<ul>
	<li>Do <b>NOT</b> use special characters, spaces, etc. in your username. Please use only letters, digits, - or _ (3 to 30 characters).</li>
	<li>After sign up you will receive a verification link on the email registered. Verify by clicking on the link and then login. <u>You won't be able to login unless you have verified your account.</u></li>
	<li>Also, try to use a desktop or laptops as much as possible. Some levels might require it.</li>
</ul>
</main>

<?php
//include header template
require('layout/footer.php');
?>
