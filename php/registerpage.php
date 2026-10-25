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
	
	if(mb_strlen($institute) < 3 || mb_strlen($institute) > 100){
		$error[] = INSTITUTE_RULE;
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
				$stmt->execute(array(':active' => hash('sha256', $activation), ':member_id' => $id));
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
					':active' => hash('sha256', $activation),
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
	<label data-tooltip="<?= h(USERNAME_RULE) ?>">Username
		<input type="text" name="username" required pattern="<?= h(USERNAME_PATTERN) ?>" maxlength="30" autocomplete="username" aria-description="<?= h(USERNAME_RULE) ?>" value="<?php if (isset($error)) { echo h($_POST['username'] ?? ''); } ?>">
	</label>
	<label data-tooltip="A valid email address. Your activation link is sent here.">Email address
		<input type="email" name="email" required autocomplete="email" value="<?php if (isset($error)) { echo h($_POST['email'] ?? ''); } ?>">
	</label>
	<label data-tooltip="<?= h(INSTITUTE_RULE) ?>">Institution
		<input type="text" name="ins" required minlength="3" maxlength="100" aria-description="<?= h(INSTITUTE_RULE) ?>" value="<?php if (isset($error)) { echo h($_POST['ins'] ?? ''); } ?>">
	</label>
	<label data-tooltip="<?= h(PASSWORD_RULE) ?>">Password
		<span class="field-check">
			<input type="password" name="password" required pattern="<?= h(PASSWORD_PATTERN) ?>" autocomplete="new-password" aria-description="<?= h(PASSWORD_RULE) ?>">
			<span class="check" aria-hidden="true"></span>
		</span>
	</label>
	<label data-tooltip="Type the same password again.">Confirm password
		<input type="password" name="passwordConfirm" required autocomplete="new-password">
	</label>
	<input type="submit" name="submit" value="Register">
</form>
<h3>General Instructions</h3>
<ul>
	<li>After sign up you will receive a verification link on the email registered. Verify by clicking on the link and then login. <u>You won't be able to login unless you have verified your account.</u></li>
	<li>Also, try to use a desktop or laptops as much as possible. Some levels might require it.</li>
</ul>
</main>

<?php
//include header template
require('layout/footer.php');
?>
