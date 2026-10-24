<?php

// Replace this with your own email address
$siteOwnersEmail = 'samdon97@gmail.com';
require_once(__DIR__ . '/contactEmail.php');


if($_POST) {

   $name = is_string($_POST['contactName'] ?? null) ? trim(stripslashes($_POST['contactName'])) : '';
   $email = is_string($_POST['contactEmail'] ?? null) ? trim(stripslashes($_POST['contactEmail'])) : '';
   $subject = is_string($_POST['contactSubject'] ?? null) ? trim(stripslashes($_POST['contactSubject'])) : '';
   $subject = str_replace(array("\r", "\n"), ' ', $subject);
   $contact_message = is_string($_POST['contactMessage'] ?? null) ? trim(stripslashes($_POST['contactMessage'])) : '';

   // Check Name
	if (strlen($name) < 2) {
		$error['name'] = "Please enter your name.";
	}
	// Check Email
	if (!is_valid_contact_email($email)) {
		$error['email'] = "Please enter a valid email address.";
	}
	// Check Message
	if (strlen($contact_message) < 15) {
		$error['message'] = "Please enter your message. It should have at least 15 characters.";
	}
   // Subject
	if ($subject == '') { $subject = "Contact Form Submission"; }


   // Set Message
   $message .= "Email from: " . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />";
	$message .= "Email address: " . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<br />";
   $message .= "Message: <br />";
   $message .= htmlspecialchars($contact_message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   $message .= "<br /> ----- <br /> This email was sent from your site's contact form. <br />";

   // Email Headers
	$headers = "From: " . $siteOwnersEmail . "\r\n";
	$headers .= "Reply-To: " . $email . "\r\n";
 	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=UTF-8\r\n";


   if (!$error) {

      ini_set("sendmail_from", $siteOwnersEmail); // for windows server
      $mail = mail($siteOwnersEmail, $subject, $message, $headers);

		if ($mail) { echo "OK"; }
      else { echo "Something went wrong. Please try again."; }
		
	} # end if - no validation error

	else {

		$response = (isset($error['name'])) ? $error['name'] . "<br /> \n" : null;
		$response .= (isset($error['email'])) ? $error['email'] . "<br /> \n" : null;
		$response .= (isset($error['message'])) ? $error['message'] . "<br />" : null;
		
		echo $response;

	} # end if - there was a validation error

}

?>
