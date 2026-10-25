<?php
require_once('includes/config.php');

if (!$user->is_logged_in()) {
	header('Location: login.php');
	exit;
}

if (!is_quiz_master($db, $_SESSION['memberID'] ?? null)) {
	http_response_code(403);
	exit('Quiz-master access required.');
}

$eventOpen = game_event_is_open($db);
$hasQuestions = count(available_question_pages()) > 0;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$action = $_POST['event_action'] ?? '';
	if (!in_array($action, array('open', 'close'), true)) {
		http_response_code(400);
		exit('Invalid event action.');
	}
	if ($action === 'open' && !$hasQuestions) {
		http_response_code(409);
		exit('The event cannot be opened because no questions are available.');
	}
	$update = $db->prepare('UPDATE event_state SET is_open = :is_open WHERE singleton_id = 1');
	$update->execute(array(':is_open' => $action === 'open' ? 1 : 0));
	header('Location: quizmaster.php?updated=1');
	exit;
}

$title = 'Manage event';
require('layout/header.php');
?>
<main class="container">
	<h1>Manage event</h1>
	<p>Current status: <strong><?php echo $eventOpen ? 'Open' : 'Closed'; ?></strong></p>
	<?php if (!$hasQuestions): ?>
		<p class="alert alert-warning">No player questions are available. Keep the event closed until new content is ready.</p>
	<?php endif; ?>
	<?php if (isset($_GET['updated'])): ?>
		<p class="alert alert-success">Event status updated.</p>
	<?php endif; ?>
	<?php if ($hasQuestions || $eventOpen): ?>
	<form method="post">
		<?= csrf_field() ?>
		<input type="hidden" name="event_action" value="<?php echo $eventOpen ? 'close' : 'open'; ?>">
		<button class="btn btn-primary" type="submit"><?php echo $eventOpen ? 'Close event' : 'Open event'; ?></button>
	</form>
	<?php endif; ?>
	<p><a href="memberpage.php">Back to dashboard</a></p>
</main>
<?php require('layout/footer.php'); ?>
