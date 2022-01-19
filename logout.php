<?php require('includes/config.php');
include "database.php";
//logout
$user->logout(); 
$query = "UPDATE members set logincount=logincount-1 WHERE username LIKE '".$_SESSION['username']."' ";
$result = $mysqli->query($query) or die($mysqli->error);
//logged in return to index page
header('Location: registerpage.php');
exit;
?>