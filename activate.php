<?php
require('includes/config.php');
include 'increment.php';

//collect values from the url
$memberID = trim($_GET['x']);
$active = trim($_GET['y']);
//$ky=generateKey();
//if id is number and the active token is not empty carry on
if(is_numeric($memberID) && !empty($active)){
	//update users record set the active column to Yes where the memberID and active value match the ones provided in the array
	echo "Activating..";
	$prstmt="UPDATE members SET active = 'Yes',level=0,lastsolvetime=addtime(NOW(),'5:30:00'),lockedq=0 WHERE memberID = :memberID AND active =:active";
	$stmt = $db->prepare($prstmt);
	$stmt->execute(array(
		':memberID' => $memberID,
		':active' => "$active",
	));

	echo "....";
	//if the row was updated redirect the user
	if($stmt->rowCount() == 1){
	    echo "Success!";
		//redirect to login page
		header('Location: login.php?action=active');
		exit;

	} else {
		echo "Your account could not be activated."; 
	}
}
?>
