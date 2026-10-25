<?php require('includes/config.php'); 
//define page title
$title = 'Conquistar | Dashboard';
if(!$user->is_logged_in()){ header('Location: login.php'); exit; }

$stmt = $db->prepare("SELECT level,email FROM members WHERE memberID = :member_id AND active = 'Yes'");
$stmt->execute(array(':member_id' => $_SESSION['memberID']));
$res = $stmt->fetch();
if (!$res) {
	$user->logout();
	header('Location: login.php');
	exit;
}
$level = max(0, (int) $res['level']);
$questionPages = glob(__DIR__ . '/questions/q_*.html') ?: array();
$totalQuestions = count(array_filter($questionPages, function ($path) {
	return preg_match('/\Aq_[0-9]+\.html\z/D', basename($path)) === 1;
}));
$solvedQuestions = min($level, $totalQuestions);
$isComplete = $totalQuestions > 0 && $level >= $totalQuestions;
$eventOpen = game_event_is_open($db);
$isQuizMaster = is_quiz_master($db, $_SESSION['memberID'] ?? null);
require('layout/header.php');
?>

<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
				<h2>Welcome <?php echo h($_SESSION['username']); ?></h2>
				<p>Progress: <?php echo h($solvedQuestions); ?> of <?php echo h($totalQuestions); ?> puzzles solved.</p>
				<progress value="<?php echo h($solvedQuestions); ?>" max="<?php echo h(max(1, $totalQuestions)); ?>">
					<?php echo h($solvedQuestions); ?> of <?php echo h($totalQuestions); ?>
				</progress>
				<p>Event status: <strong><?php echo $eventOpen ? 'Open' : 'Closed'; ?></strong></p>
				<p>
					<?php if (!$eventOpen): ?>
						The event is closed. Check back when the quiz master reopens it.
					<?php elseif ($isComplete): ?>
						You have completed every puzzle. Congratulations!
					<?php else: ?>
						<a class="btn btn-primary" href="process.php">Continue to puzzle <?php echo h($level + 1); ?></a>
					<?php endif; ?>
				</p>
				<p>Need a hint? Visit the <a href="https://www.facebook.com/ConquistarHunt" target="_blank" rel="noopener noreferrer">community hints</a>.</p>
				<p>Account email: <?php echo h($res["email"]); ?></p>
				<p><a href="delete-account.php">Delete my account</a></p>
				<?php if ($isQuizMaster): ?>
					<p><a href="quizmaster.php">Manage event</a></p>
				<?php endif; ?>
				<h3><a href="leaderboard.php">Leaderboard</a></h3>
				<form method="post" action="logout.php">
					<?= csrf_field() ?>
					<button type="submit" class="btn btn-link">Logout</button>
				</form>
				<hr>

		</div>
	</div>


</div>

<?php 
//include header template
require('layout/footer.php'); 
?>
