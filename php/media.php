<?php
require_once('includes/config.php');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) {
	header('Allow: GET, HEAD');
	http_response_code(405);
	exit;
}

$file = $_GET['file'] ?? '';
$media = require(__DIR__ . '/includes/media-catalog.php');

if (!is_string($file) || !isset($media[$file])) {
	http_response_code(404);
	exit;
}

$memberId = filter_var($_SESSION['memberID'] ?? null, FILTER_VALIDATE_INT);
if (!$user->is_logged_in() || $memberId === false || $memberId < 1) {
	http_response_code(403);
	exit;
}

$stmt = $db->prepare("SELECT level FROM members WHERE memberID = :member_id AND active = 'Yes'");
$stmt->execute(array(':member_id' => $memberId));
$player = $stmt->fetch();
$playerLevel = $player ? filter_var($player['level'], FILTER_VALIDATE_INT) : false;
if ($playerLevel === false || $playerLevel < $media[$file][1]) {
	http_response_code(403);
	exit;
}

session_write_close();

$path = __DIR__ . '/' . $media[$file][0];
if (!is_file($path) || !is_readable($path)) {
	http_response_code(404);
	exit;
}

$size = filesize($path);
if ($size === false || $size < 1) {
	http_response_code(404);
	exit;
}
$start = 0;
$end = $size - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if (is_string($range) && preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $matches)
	&& ($matches[1] !== '' || $matches[2] !== '')) {
	if ($matches[1] === '') {
		$suffix = (int) $matches[2];
		if ($suffix < 1) {
			http_response_code(416);
			header('Content-Range: bytes */' . $size);
			exit;
		}
		$start = max(0, $size - $suffix);
	} else {
		$start = (int) $matches[1];
	}
	$end = $matches[2] === '' ? $end : min($end, (int) $matches[2]);
	if ($start > $end || $start >= $size) {
		http_response_code(416);
		header('Content-Range: bytes */' . $size);
		exit;
	}
	http_response_code(206);
	header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

$handle = fopen($path, 'rb');
if ($handle === false) {
	http_response_code(404);
	exit;
}
while (ob_get_level() > 0) {
	ob_end_clean();
}
header('Content-Type: ' . $media[$file][2]);
header('Cache-Control: private, no-store');
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($end - $start + 1));
if ($media[$file][2] === 'application/zip') {
	header('Content-Disposition: attachment; filename="' . basename($path) . '"');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
	fclose($handle);
	exit;
}
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
	$chunk = fread($handle, min(8192, $remaining));
	if ($chunk === false || $chunk === '') {
		break;
	}
	echo $chunk;
	$remaining -= strlen($chunk);
}
fclose($handle);
