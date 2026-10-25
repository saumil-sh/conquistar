<?php
require('includes/config.php');

// Activation is a single-use capability; leave player progress untouched.
$memberID = is_string($_GET['x'] ?? null) ? $_GET['x'] : '';
$active = is_string($_GET['y'] ?? null) ? $_GET['y'] : '';
if (ctype_digit($memberID) && $memberID !== '' && $active !== '') {
	$stmt = $db->prepare("UPDATE members SET active = 'Yes', activationExpiresAt = NULL WHERE memberID = :memberID AND active = :active AND activationExpiresAt > NOW()");
	$stmt->execute(array(
		':memberID' => $memberID,
		':active' => $active,
	));

	if ($stmt->rowCount() === 1) {
		header('Location: login.php?action=active');
		exit;
	}
}

http_response_code(400);
echo 'Your account could not be activated.';
?>
