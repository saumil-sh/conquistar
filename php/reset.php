<?php require('includes/config.php');

//if logged in redirect to members page
if( $user->is_logged_in() ){ header('Location: memberpage.php'); exit; }

//if form has been submitted process it
if(isset($_POST['submit'])){
	$email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error[] = 'Please enter a valid email address';
	} else {
		try {
			$clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
			$canSend = rate_limit_allow($db, 'reset-ip', $clientIp, 250, 3600);
			if ($canSend) {
				$canSend = rate_limit_allow($db, 'reset-email', strtolower(trim($email)), 3, 3600);
			}

			if ($canSend) {
				$stmt = $db->prepare('SELECT memberID, email FROM members WHERE email = :email');
				$stmt->execute(array(':email' => $email));
				$row = $stmt->fetch(PDO::FETCH_ASSOC);

				if ($row) {
					$token = bin2hex(random_bytes(32));
					$stmt = $db->prepare("UPDATE members SET resetToken = :token, resetComplete = 'No', resetExpiresAt = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE memberID = :member_id");
					$stmt->execute(array(':member_id' => $row['memberID'], ':token' => $token));

					$resetUrl = h(DIR . 'resetPassword.php?key=' . rawurlencode($token));
					$body = "<p>Someone requested that the password be reset.</p>
					<p>If this was a mistake, just ignore this email and nothing will happen.</p>
					<p>To reset your password, visit the following address: <a href='" . $resetUrl . "'>" . $resetUrl . "</a></p>";

					$mail = new Mail();
					$mail->setFrom(SITEEMAIL);
					$mail->addAddress($row['email']);
					$mail->subject('Password Reset');
					$mail->body($body);
					if (!$mail->send()) {
						http_response_code(503);
						$error[] = 'We could not send the reset email. Please try again later.';
					}
				}
			}
		} catch (PDOException $e) {
			error_log($e->getMessage());
			http_response_code(500);
			$error[] = 'We could not process the request. Please try again later.';
		}

		if (!isset($error)) {
			header('Location: login.php?action=reset');
			exit;
		}
	}
}

//define page title
$title = 'Reset Account';

//include header template
require('layout/header.php');
?>
<main class="container">
<form method="post" action="" autocomplete="off">
	<?= csrf_field() ?>
	<h2>Reset Password</h2>
	<p><a href="login.php">Back to login page</a></p>
	<?php if (isset($error)): foreach ($error as $message): ?>
	<p class="alert alert-danger"><?= h($message) ?></p>
	<?php endforeach; endif; ?>
	<input type="email" name="email" id="email" placeholder="Email" aria-label="Email">
	<input type="submit" name="submit" value="Send Reset Link">
</form>
</main>

<?php
//include header template
require('layout/footer.php');
?>
