<?php require('includes/config.php'); 
//define page title
$title = 'Conquistar | Dashboard';
require('layout/header.php'); 
if(!$user->is_logged_in()){ header('Location: login.php'); } 

//include header template

include 'database.php';
$query = "SELECT level,email FROM members WHERE username LIKE '".$_SESSION['username']."' ";
$result = $mysqli->query($query) or die($mysqli->error);
$res = $result->fetch_assoc();
?>

<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
				<h2>Welcome <?php echo $_SESSION['username']; ?></h2>
				<p>Your current level: <?php echo $res["level"];?> </p>
				<p>Please note that all mail communication will take place via/on: <?php echo $res["email"];?> </p>
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
