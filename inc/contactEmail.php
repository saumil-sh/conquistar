<?php
function is_valid_contact_email($email) {
	return is_string($email)
		&& preg_match('/^[a-z0-9&\'\.\-_+]+@[a-z0-9-]+\.([a-z0-9-]+\.)*+[a-z]{2,}\z/i', $email) === 1;
}
