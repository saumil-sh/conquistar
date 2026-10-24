<?php
require_once('includes/config.php');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)) {
	header('Allow: GET, HEAD');
	http_response_code(405);
	exit;
}

$file = $_GET['file'] ?? '';
$media = array(
	'fb' => array('images/fb_.png', 2, 'image/png'),
	'dyel' => array('images/dyel_.png', 3, 'image/png'),
	'eeyore' => array('images/eeyore_.png', 4, 'image/png'),
	'papa' => array('images/papa_.png', 5, 'image/png'),
	'bingo' => array('images/bingo_.png', 6, 'image/png'),
	'killingpeople' => array('images/killingpeople_.png', 7, 'image/png'),
	'perfection' => array('images/perfection_.png', 10, 'image/png'),
	'matchbox' => array('images/matchbox_.png', 12, 'image/png'),
	'movies' => array('images/movies_.png', 13, 'image/png'),
	'collage' => array('images/collage_.png', 15, 'image/png'),
	'baadshah' => array('images/baadshah_.png', 20, 'image/png'),
	'fish' => array('images/fish_.png', 21, 'image/png'),
	'sparta-video' => array('accessible/sparta.mp4', 8, 'video/mp4'),
	'challenge-video' => array('accessible/Is that even a challenge.mp4', 9, 'video/mp4'),
	'song-video' => array('accessible/song.mp4', 11, 'video/mp4'),
	'three-idiots-video' => array('accessible/3idiots.mp4', 16, 'video/mp4'),
	'fakes-video' => array('accessible/fakes.mp4', 18, 'video/mp4'),
	'matchbox-archive' => array('accessible/matchbox.zip', 12, 'application/zip'),
	'baap-archive' => array('accessible/baap.zip', 17, 'application/zip'),
	'soft-archive' => array('accessible/soft.zip', 19, 'application/zip'),
	'animal-archive' => array('accessible/its_was_an_animal.zip', 20, 'application/zip'),
);

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
