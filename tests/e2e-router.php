<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && (strpos($path, '/tests/') === 0 || $path === '/tests')) {
	http_response_code(404);
	exit;
}
return false;
