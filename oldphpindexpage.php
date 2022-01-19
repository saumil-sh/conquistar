<?php require('includes/config.php');

//if logged in redirect to members page
if( $user->is_logged_in() ){ header('Location: memberpage.php'); }?>
<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
<?php
echo "<form role='form' method='post' action='' autocomplete='off'>";
echo "<h2> Welcome to Tapmaq 2019</h2>";
echo "<p>Registration is closed. The game ends <b>at 8:00pm today (01-Sept-2019)</b></p>";
echo "To login, click <a href='login.php'>here</a>";
echo "</form>";
$title = 'Tapmaq - Find the person';

//include header template
require('layout/header.php');
?>
</div>
</div>
</div>