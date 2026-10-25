<?php
require_once('includes/config.php');

if (!$user->is_logged_in()) {
	header('Location: login.php');
	exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'POST'), true)) {
	header('Allow: GET, POST');
	http_response_code(405);
	exit('Method not allowed.');
}

$memberId = filter_var($_SESSION['memberID'] ?? null, FILTER_VALIDATE_INT);
if ($memberId === false || $memberId < 1) {
	http_response_code(403);
	exit('Your account session is invalid. Please sign in again.');
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	if (($_POST['confirm_delete'] ?? '') !== '1') {
		http_response_code(400);
		exit('Confirm account deletion to continue.');
	}

	try {
		$db->beginTransaction();
		$member = $db->prepare("SELECT memberID FROM members WHERE memberID = :member_id AND active = 'Yes' FOR UPDATE");
		$member->execute(array(':member_id' => $memberId));
		if (!$member->fetch()) {
			$db->rollBack();
			$user->logout();
			header('Location: login.php');
			exit;
		}

		$answers = $db->prepare('DELETE FROM answerlog WHERE memberID = :member_id');
		$answers->execute(array(':member_id' => $memberId));
		$hints = $db->prepare('DELETE FROM hint_uses WHERE memberID = :member_id');
		$hints->execute(array(':member_id' => $memberId));
		$delete = $db->prepare("DELETE FROM members WHERE memberID = :member_id AND active = 'Yes'");
		$delete->execute(array(':member_id' => $memberId));
		if ($delete->rowCount() !== 1) {
			throw new RuntimeException('Account deletion did not remove one member.');
		}
		$db->commit();
		$user->logout();
		header('Location: registerpage.php?action=deleted');
		exit;
	} catch (Throwable $e) {
		if ($db->inTransaction()) {
			$db->rollBack();
		}
		error_log($e->getMessage());
		http_response_code(500);
		$error = 'Your account could not be deleted. Please try again later.';
	}
}

$title = 'Delete account';
require('layout/header.php');
?>
<main class="container">
	<h1>Delete account</h1>
	<?php if ($error !== null): ?>
		<p class="alert alert-danger"><?php echo h($error); ?></p>
	<?php endif; ?>
	<p>This permanently deletes your account, progress, and answer history.</p>
	<form method="post">
		<?= csrf_field() ?>
		<button class="btn-danger" type="submit" name="confirm_delete" value="1">Delete my account</button>
		<a role="button" class="secondary" href="memberpage.php">Cancel</a>
	</form>
</main>
<?php require('layout/footer.php'); ?>
