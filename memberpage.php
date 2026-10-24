<?php require('includes/config.php'); 
//define page title
$title = 'Conquistar | Dashboard';
require('layout/header.php'); 
if(!$user->is_logged_in()){ header('Location: login.php'); } 

$stmt = $db->prepare('SELECT level,email FROM members WHERE username = :username');
$stmt->execute(array(':username' => $_SESSION['username']));
$res = $stmt->fetch();
?>

<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
				<h2>Welcome <?php echo h($_SESSION['username']); ?></h2>
				<p>Your current level: <?php echo h($res["level"]);?> </p>
				<p>Please note that all mail communication will take place via/on: <?php echo h($res["email"]);?> </p>
				<!--<p>Game <b>has officially ended</b></p>-->
				<p>The game officially <b>Ends!!</b></p>
				<p><font color="red"><strike>Start</strike></font></p>
				<!--<p><a href="https://conquistar.iplug.website/process.php">Start</a></p>-->
				<h3><a href="leaderboard.php">Leaderboard</a></h3>
				<p><a href='logout.php'>Logout</a></p>
				<hr>

		</div>
	</div>


</div>

<?php 
//include header template
require('layout/footer.php'); 
?>
