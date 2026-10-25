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
	if(!matches_rule($password, PASSWORD_PATTERN)){
		$error[] = PASSWORD_RULE;
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
<main class="container">
<?php if (isset($stop)): ?>
	<p class="alert alert-danger"><?= h($stop) ?></p>
<?php else: ?>
<form method="post" action="" autocomplete="off">
	<?= csrf_field() ?>
	<h2>Change Password</h2>
	<?php if (isset($error)): foreach ($error as $message): ?>
	<p class="alert alert-danger"><?= h($message) ?></p>
	<?php endforeach; endif; ?>
	<input type="password" name="password" id="password" placeholder="Password" aria-label="Password">
	<input type="password" name="passwordConfirm" id="passwordConfirm" placeholder="Confirm Password" aria-label="Confirm Password">
	<input type="submit" name="submit" value="Change Password">
</form>
<?php endif; ?>
</main>

<?php 
//include header template
require('layout/footer.php'); 
?>