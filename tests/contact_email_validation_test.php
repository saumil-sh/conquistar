<?php
require_once(__DIR__ . '/../inc/contactEmail.php');

$validEmails = array(
	'player@example.com',
	'player@example.org',
	'player@example.de',
);
foreach ($validEmails as $email) {
	if (!is_valid_contact_email($email)) {
		fwrite(STDERR, "Expected valid contact email: $email\n");
		exit(1);
	}
}

if (is_valid_contact_email("player@example.com\r\nBcc: attacker@example.org")) {
	fwrite(STDERR, "Contact email must not accept header line breaks.\n");
	exit(1);
}

echo "Contact email validation checks passed.\n";
