<?php require('includes/config.php');
$stmt = $db->prepare('UPDATE members SET logincount = logincount - 1 WHERE username = :username');
$stmt->execute(array(':username' => $_SESSION['username']));
$user->logout();
//logged in return to index page
header('Location: registerpage.php');
exit;
?>