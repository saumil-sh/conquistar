<?php
require_once(__DIR__ . '/test_db_guard.php');
$testDatabase = require_test_database_environment();

$databaseHost = getenv('TEST_DB_HOST');
if (!is_string($databaseHost)) {
	fwrite(STDERR, "Set TEST_DB_HOST to localhost or another loopback address.\n");
	exit(2);
}
if (!in_array($databaseHost, array('localhost', '127.0.0.1', '::1'), true)) {
	fwrite(STDERR, "End-to-end tests only connect to a loopback database host.\n");
	exit(2);
}
$databaseUser = getenv('TEST_DB_USER');
$databasePassword = getenv('TEST_DB_PASSWORD');
if (!is_string($databaseUser) || !is_string($databasePassword)) {
	fwrite(STDERR, "Set TEST_DB_USER and TEST_DB_PASSWORD for the test database only.\n");
	exit(2);
}

$port = getenv('TEST_HTTP_PORT') ?: '18765';
if (!ctype_digit((string) $port) || (int) $port < 1024 || (int) $port > 65535) {
	fwrite(STDERR, "TEST_HTTP_PORT must be a local TCP port between 1024 and 65535.\n");
	exit(2);
}
$portProbe = @stream_socket_server('tcp://127.0.0.1:' . (int) $port, $portError, $portMessage);
if (!$portProbe) {
	fwrite(STDERR, "TEST_HTTP_PORT is already in use or unavailable; choose another local port.\n");
	exit(2);
}
fclose($portProbe);

$runtimeDirectory = __DIR__ . '/.runtime';
if (file_exists($runtimeDirectory)) {
	fwrite(STDERR, "Remove tests/.runtime after reviewing its contents, then retry.\n");
	exit(2);
}
if (!mkdir($runtimeDirectory, 0700)) {
	fwrite(STDERR, "Could not create the private test runtime directory.\n");
	exit(2);
}
$sessionDirectory = $runtimeDirectory . '/sessions';
if (!mkdir($sessionDirectory, 0700)) {
	rmdir($runtimeDirectory);
	fwrite(STDERR, "Could not create the private test session directory.\n");
	exit(2);
}

$capturePath = $runtimeDirectory . '/mail.jsonl';
file_put_contents($capturePath, '');
$root = realpath(__DIR__ . '/..');
$baseUrl = 'http://127.0.0.1:' . $port . '/';
$server = null;
$db = null;
$memberIds = array();
$username = 'e2e' . bin2hex(random_bytes(5));
$registeredMemberId = null;
$questionTouched = false;
$questionLevelOneTouched = false;
$questionPagePaths = array();
$testDataPrepared = false;
$originalEventOpen = null;
$eventStateTouched = false;
$closeEventTriggerCreated = false;
$exitCode = 0;

function e2e_check($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function e2e_request($baseUrl, $path, $method = 'GET', $fields = array(), $cookie = '')
{
	$url = preg_match('/\Ahttps?:\/\//i', $path) ? $path : $baseUrl . ltrim($path, '/');
	$headers = array('Accept: */*');
	if ($cookie !== '') {
		$headers[] = 'Cookie: PHPSESSID=' . $cookie;
	}
	$options = array(
		'method' => $method,
		'header' => implode("\r\n", $headers),
		'ignore_errors' => true,
		'follow_location' => 0,
		'timeout' => 10,
	);
	if ($method === 'POST') {
		$options['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
		$options['content'] = http_build_query($fields);
	}
	$http_response_header = array();
	$body = @file_get_contents($url, false, stream_context_create(array('http' => $options)));
	e2e_check($body !== false, "Could not reach the local test server.");

	$status = 0;
	$newCookie = $cookie;
	foreach ($http_response_header as $header) {
		if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
			$status = (int) $matches[1];
		}
		if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]*)/i', $header, $matches)) {
			$newCookie = $matches[1];
		}
	}
	return array('status' => $status, 'body' => $body, 'headers' => $http_response_header, 'cookie' => $newCookie);
}

function e2e_csrf($html)
{
	if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
		throw new RuntimeException('A form did not contain its CSRF token.');
	}
	return $matches[1];
}

function e2e_location(array $response)
{
	foreach ($response['headers'] as $header) {
		if (stripos($header, 'Location:') === 0) {
			return trim(substr($header, strlen('Location:')));
		}
	}
	return '';
}

function e2e_login($baseUrl, $username, $password)
{
	$response = e2e_request($baseUrl, 'login.php');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'login.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'username' => $username,
		'password' => $password,
		'submit' => 'Login',
	), $cookie);
	e2e_check($response['status'] === 302, 'Login failed while preparing the stale-session test.');
	return $response['cookie'];
}

function e2e_mail_messages($path)
{
	$messages = array();
	foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$message = json_decode($line, true);
		if (is_array($message)) {
			$messages[] = $message;
		}
	}
	return $messages;
}

try {
	try {
		$db = new PDO(
			'mysql:host=' . $databaseHost . ';dbname=' . $testDatabase . ';charset=utf8mb4',
			$databaseUser,
			$databasePassword,
			array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
		);
	} catch (PDOException $e) {
		throw new RuntimeException('Could not connect to the explicitly named local test database.');
	}

	foreach (array('members', 'questions', 'answerlog', 'request_limits') as $table) {
		$count = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
		e2e_check($count === 0, 'Use a fresh, isolated test database with empty ' . $table . '.');
	}
	$testDataPrepared = true;
	$eventStateRows = (int) $db->query('SELECT COUNT(*) FROM event_state')->fetchColumn();
	e2e_check($eventStateRows === 1, 'The isolated test database must contain its single event-state row.');
	$initialEventOpen = $db->query('SELECT is_open FROM event_state WHERE singleton_id = 1')->fetchColumn();
	e2e_check($initialEventOpen !== false && in_array((string) $initialEventOpen, array('0', '1'), true), 'The isolated test database event state is invalid.');
	$originalEventOpen = (int) $initialEventOpen;
	$eventStateTouched = true;
	$eventUpdate = $db->prepare('UPDATE event_state SET is_open = :is_open WHERE singleton_id = 1');
	$eventUpdate->execute(array(':is_open' => 1));
	e2e_check((int) $db->query('SELECT is_open FROM event_state WHERE singleton_id = 1')->fetchColumn() === 1, 'Could not open the test event.');

	$testAnswerHash = password_hash('testanswer', PASSWORD_BCRYPT);
	$stmt = $db->prepare('INSERT INTO questions (qID, Answer) VALUES (0, :answer)');
	$stmt->execute(array(':answer' => $testAnswerHash));
	$questionTouched = true;

	$environment = array(
		'APP_ENV' => 'test',
		'APP_URL' => $baseUrl,
		'DB_HOST' => $databaseHost,
		'DB_NAME' => $testDatabase,
		'DB_USER' => $databaseUser,
		'DB_PASSWORD' => $databasePassword,
		'TEST_DB_NAME' => $testDatabase,
		'TEST_MAIL_CAPTURE' => realpath($runtimeDirectory) . '/mail.jsonl',
	);
	$command = array(PHP_BINARY, '-d', 'session.save_path=' . $sessionDirectory, '-S', '127.0.0.1:' . (int) $port, '-t', $root, __DIR__ . '/e2e-router.php');
	$server = proc_open($command, array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('file', '/dev/null', 'a'),
		2 => array('file', '/dev/null', 'a'),
	), $pipes, $root, $environment);
	e2e_check(is_resource($server), 'Could not start the local PHP test server.');

	$ready = false;
	for ($attempt = 0; $attempt < 40; $attempt++) {
		$socket = @fsockopen('127.0.0.1', (int) $port, $errorCode, $errorMessage, 0.2);
		if ($socket) {
			fclose($socket);
			$ready = true;
			break;
		}
		usleep(100000);
	}
	e2e_check($ready, 'The local PHP test server did not become ready.');

	$response = e2e_request($baseUrl, 'registerpage.php');
	e2e_check($response['status'] === 200, 'Registration form did not load.');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'registerpage.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'username' => $username,
		'email' => $username . '@example.test',
		'password' => 'old-password',
		'passwordConfirm' => 'old-password',
		'ins' => 'Test institute',
		'submit' => 'Register',
	), $cookie);
	e2e_check($response['status'] === 302, 'Registration did not complete.');

	$stmt = $db->prepare('SELECT memberID, active, level FROM members WHERE username = :username');
	$stmt->execute(array(':username' => $username));
	$member = $stmt->fetch();
	e2e_check($member && $member['active'] !== 'Yes', 'Registration did not create an inactive account.');
	$registeredMemberId = (int) $member['memberID'];
	$memberIds[] = $registeredMemberId;
	$messages = e2e_mail_messages($capturePath);
	e2e_check(count($messages) === 1 && preg_match('/activate\.php\?x=([0-9]+)&amp;y=([a-f0-9]{64})/', $messages[0]['body'], $activation), 'Registration activation email was not captured.');
	$activationResponse = e2e_request($baseUrl, 'activate.php?x=' . $activation[1] . '&y=' . $activation[2]);
	e2e_check($activationResponse['status'] === 302, 'Activation link did not activate the account.');
	$replayResponse = e2e_request($baseUrl, 'activate.php?x=' . $activation[1] . '&y=' . $activation[2]);
	e2e_check($replayResponse['status'] === 400, 'Activation link replay was accepted.');

	$response = e2e_request($baseUrl, 'login.php');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'login.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'username' => $username,
		'password' => 'old-password',
		'submit' => 'Login',
	), $cookie);
	e2e_check($response['status'] === 302, 'Login failed after activation.');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'process.php', 'GET', array(), $cookie);
	e2e_check(strpos($response['body'], 'temporarily unavailable while questions are being updated') !== false, 'Retired puzzles were not reported as unavailable.');
	e2e_check(strpos($response['body'], '<form action="process.php" method="post">') === false, 'A retired puzzle still rendered an answer form.');
	$unavailableAnswer = e2e_request($baseUrl, 'process.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'ans' => 'testanswer',
	), $cookie);
	e2e_check($unavailableAnswer['status'] === 200 && strpos($unavailableAnswer['body'], 'temporarily unavailable while questions are being updated') !== false, 'A retired puzzle accepted an answer submission.');
	$closedState = $db->prepare('SELECT level FROM members WHERE memberID = :id');
	$closedState->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $closedState->fetchColumn() === 0, 'A retired puzzle advanced the player.');
	$answerCount = $db->prepare('SELECT COUNT(*) FROM answerlog WHERE memberID = :id');
	$answerCount->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $answerCount->fetchColumn() === 0, 'A retired puzzle recorded an answer attempt.');

	foreach (array(0, 1) as $testLevel) {
		$questionPath = $root . '/questions/q_' . $testLevel . '.html';
		e2e_check(!file_exists($questionPath) && !is_link($questionPath), 'Test question path already exists.');
		e2e_check(file_put_contents($questionPath, '<p>Test-only question.</p>', LOCK_EX) !== false, 'Could not create a temporary test question.');
		$questionPagePaths[] = $questionPath;
	}
	$response = e2e_request($baseUrl, 'process.php', 'GET', array(), $cookie);
	$eventUpdate->execute(array(':is_open' => 0));
	$closedAnswer = e2e_request($baseUrl, 'process.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'ans' => 'testanswer',
	), $cookie);
	e2e_check($closedAnswer['status'] === 200 && strpos($closedAnswer['body'], 'The event is closed.') !== false, 'An answer was not rejected while the event was closed.');
	e2e_check(strpos($closedAnswer['body'], '<form action="process.php" method="post">') === false, 'A closed event still rendered an answer form.');
	$closedState->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $closedState->fetchColumn() === 0, 'A closed event advanced the player.');
	$answerCount->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $answerCount->fetchColumn() === 0, 'A closed event recorded an answer attempt.');
	$eventUpdate->execute(array(':is_open' => 1));
	e2e_check((int) $db->query('SELECT is_open FROM event_state WHERE singleton_id = 1')->fetchColumn() === 1, 'Could not reopen the test event.');
	$response = e2e_request($baseUrl, 'process.php', 'GET', array(), $cookie);
	$response = e2e_request($baseUrl, 'process.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'ans' => 'testanswer',
	), $cookie);
	e2e_check($response['status'] === 302, 'Correct answer did not advance the player.');
	$state = $db->prepare('SELECT level, lastsolvetime FROM members WHERE memberID = :id');
	$state->execute(array(':id' => $registeredMemberId));
	$player = $state->fetch();
	e2e_check((int) $player['level'] === 1, 'Correct answer did not advance exactly one level.');

	$response = e2e_request($baseUrl, 'process.php', 'GET', array(), $cookie);
	$replayResponse = e2e_request($baseUrl, 'process.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'ans' => 'testanswer',
	), $cookie);
	$state->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $state->fetchColumn() === 1, 'Answer replay advanced the player again.');
	e2e_check(strpos($replayResponse['body'], 'Wrong Answer') !== false, 'Answer replay was not rejected.');

	$stmt = $db->prepare('INSERT INTO questions (qID, Answer) VALUES (1, :answer)');
	$stmt->execute(array(':answer' => $testAnswerHash));
	$questionLevelOneTouched = true;
	$triggerName = 'e2e_close_event_on_answerlog';
	$triggerCheck = $db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :trigger_name');
	$triggerCheck->execute(array(':trigger_name' => $triggerName));
	e2e_check((int) $triggerCheck->fetchColumn() === 0, 'Remove the leftover event-close test trigger before retrying.');
	$db->exec('CREATE TRIGGER e2e_close_event_on_answerlog BEFORE INSERT ON answerlog FOR EACH ROW UPDATE event_state SET is_open = 0 WHERE singleton_id = 1');
	$closeEventTriggerCreated = true;
	$response = e2e_request($baseUrl, 'process.php', 'GET', array(), $cookie);
	$midRequestClose = e2e_request($baseUrl, 'process.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'ans' => 'testanswer',
	), $cookie);
	e2e_check($midRequestClose['status'] === 200 && strpos($midRequestClose['body'], 'The event is closed.') !== false, 'The event closing during answer processing was not reported.');
	e2e_check(strpos($midRequestClose['body'], '<form action="process.php" method="post">') === false, 'An event closed mid-request still rendered an answer form.');
	$state->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $state->fetchColumn() === 1, 'An answer advanced after the event closed during the request.');
	$db->exec('DROP TRIGGER e2e_close_event_on_answerlog');
	$closeEventTriggerCreated = false;
	$eventUpdate->execute(array(':is_open' => 1));
	e2e_check((int) $db->query('SELECT is_open FROM event_state WHERE singleton_id = 1')->fetchColumn() === 1, 'Could not reopen the test event after the mid-request close.');

	$response = e2e_request($baseUrl, 'media.php?file=fb', 'GET', array(), $cookie);
	e2e_check($response['status'] === 404, 'Retired question media remained available.');
	$unauthenticatedMedia = e2e_request($baseUrl, 'media.php?file=fb');
	e2e_check($unauthenticatedMedia['status'] === 404, 'Retired question media remained available without a session.');
	$update = $db->prepare('UPDATE members SET level = 2 WHERE memberID = :id');
	$update->execute(array(':id' => $registeredMemberId));
	$mediaAfterFormerUnlock = e2e_request($baseUrl, 'media.php?file=fb', 'GET', array(), $cookie);
	e2e_check($mediaAfterFormerUnlock['status'] === 404, 'Retired media remained available after the player reached its former unlock level.');
	$privateRuntime = e2e_request($baseUrl, 'tests/.runtime/mail.jsonl');
	e2e_check($privateRuntime['status'] === 404, 'Test mail capture was reachable over HTTP.');

	$response = e2e_request($baseUrl, 'process.php', 'GET', array(), $cookie);
	$logout = e2e_request($baseUrl, 'logout.php', 'POST', array('csrf_token' => e2e_csrf($response['body'])), $cookie);
	e2e_check($logout['status'] === 302, 'Logout failed before password reset.');

	$response = e2e_request($baseUrl, 'reset.php');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'reset.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'email' => $username . '@example.test',
		'submit' => 'Sent Reset Link',
	), $cookie);
	e2e_check($response['status'] === 302, 'Password reset request did not complete.');
	$messages = e2e_mail_messages($capturePath);
	e2e_check(count($messages) === 2 && preg_match('/resetPassword\.php\?key=([a-f0-9]{64})/', $messages[1]['body'], $reset), 'Password reset email was not captured.');
	$response = e2e_request($baseUrl, 'resetPassword.php?key=' . $reset[1]);
	e2e_check($response['status'] === 200, 'Password reset form did not load.');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'resetPassword.php?key=' . $reset[1], 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'password' => 'new-password',
		'passwordConfirm' => 'new-password',
		'submit' => 'Change Password',
	), $cookie);
	e2e_check($response['status'] === 302, 'Password reset did not complete.');
	$resetReplay = e2e_request($baseUrl, 'resetPassword.php?key=' . $reset[1]);
	e2e_check(strpos($resetReplay['body'], 'invalid, expired, or has already been used') !== false, 'Password reset token replay was accepted.');

	$response = e2e_request($baseUrl, 'login.php');
	$cookie = $response['cookie'];
	$response = e2e_request($baseUrl, 'login.php', 'POST', array(
		'csrf_token' => e2e_csrf($response['body']),
		'username' => $username,
		'password' => 'new-password',
		'submit' => 'Login',
	), $cookie);
	e2e_check($response['status'] === 302, 'Login failed after password reset.');
	$cookie = $response['cookie'];
	$state->execute(array(':id' => $registeredMemberId));
	$player = $state->fetch();
	$tieName = $username . 'tie';
	$inactiveName = $username . 'inactive';
	$peerPassword = password_hash('test-password', PASSWORD_BCRYPT);
	$insertPeer = $db->prepare("INSERT INTO members
		(username, password, email, active, resetComplete, level, ip, institute, lastsolvetime, logincount)
		VALUES (:username, :password, :email, :active, 'No', :level, '127.0.0.1', 'Test institute', :solved, 0)");
	foreach (array(array($tieName, 'Yes', 2, $player['lastsolvetime']), array($inactiveName, 'No', 99, '2000-01-01 00:00:00')) as $peer) {
		$insertPeer->execute(array(
			':username' => $peer[0],
			':password' => $peerPassword,
			':email' => $peer[0] . '@example.test',
			':active' => $peer[1],
			':level' => $peer[2],
			':solved' => $peer[3],
		));
		$memberIds[] = (int) $db->lastInsertId();
	}

	$leaderboard = e2e_request($baseUrl, 'leaderboard.php', 'GET', array(), $cookie);
	e2e_check($leaderboard['status'] === 200, 'Leaderboard did not load.');
	e2e_check(strpos($leaderboard['body'], 'Your rank: 1') !== false, 'Deterministic tie rank was incorrect.');
	e2e_check(strpos($leaderboard['body'], $inactiveName) === false, 'Inactive player appeared on the leaderboard.');
	e2e_check(strpos($leaderboard['body'], $username) < strpos($leaderboard['body'], $tieName), 'Equal scores were not ordered deterministically.');

	$setInactive = $db->prepare("UPDATE members SET active = 'No' WHERE memberID = :id");
	$setInactive->execute(array(':id' => $registeredMemberId));
	$inactiveSession = e2e_request($baseUrl, 'memberpage.php', 'GET', array(), $cookie);
	e2e_check($inactiveSession['status'] === 302 && e2e_location($inactiveSession) === 'login.php', 'An inactive member session was not redirected to login.');
	e2e_check($inactiveSession['cookie'] === '', 'An inactive member session was not cleared.');
	$reactivate = $db->prepare("UPDATE members SET active = 'Yes' WHERE memberID = :id");
	$reactivate->execute(array(':id' => $registeredMemberId));

	$deleteCookie = e2e_login($baseUrl, $username, 'new-password');
	$staleCookie = e2e_login($baseUrl, $username, 'new-password');
	$deleteForm = e2e_request($baseUrl, 'delete-account.php', 'GET', array(), $deleteCookie);
	$deleted = e2e_request($baseUrl, 'delete-account.php', 'POST', array(
		'csrf_token' => e2e_csrf($deleteForm['body']),
		'confirm_delete' => '1',
	), $deleteCookie);
	e2e_check($deleted['status'] === 302 && e2e_location($deleted) === 'registerpage.php?action=deleted', 'Account deletion did not complete in the stale-session test.');
	$staleSession = e2e_request($baseUrl, 'memberpage.php', 'GET', array(), $staleCookie);
	e2e_check($staleSession['status'] === 302 && e2e_location($staleSession) === 'login.php', 'A deleted account session was not redirected to login.');
	e2e_check($staleSession['cookie'] === '', 'A deleted account session was not cleared.');
	$remainingMember = $db->prepare('SELECT COUNT(*) FROM members WHERE memberID = :id');
	$remainingMember->execute(array(':id' => $registeredMemberId));
	e2e_check((int) $remainingMember->fetchColumn() === 0, 'The stale-session test account was not deleted.');
	echo "Registration, activation, login, reset, retired-content gating, answer flow, rankings, and stale-session rejection passed.\n";
} catch (Throwable $e) {
	fwrite(STDERR, "End-to-end test failed: " . $e->getMessage() . "\n");
	$exitCode = 1;
} finally {
	if (is_resource($server)) {
		proc_terminate($server);
		proc_close($server);
	}
	foreach ($questionPagePaths as $questionPath) {
		if (file_exists($questionPath) && !unlink($questionPath)) {
			fwrite(STDERR, "Temporary question cleanup failed; remove the exact test question file.\n");
			$exitCode = 1;
		}
	}
	if ($db instanceof PDO && $closeEventTriggerCreated) {
		try {
			$db->exec('DROP TRIGGER e2e_close_event_on_answerlog');
		} catch (Throwable $e) {
			fwrite(STDERR, "Test trigger cleanup failed; inspect only the isolated test database.\n");
			$exitCode = 1;
		}
	}
	if ($db instanceof PDO && $eventStateTouched && $originalEventOpen !== null) {
		try {
			$restoreEvent = $db->prepare('UPDATE event_state SET is_open = :is_open WHERE singleton_id = 1');
			$restoreEvent->execute(array(':is_open' => $originalEventOpen));
			e2e_check((int) $db->query('SELECT is_open FROM event_state WHERE singleton_id = 1')->fetchColumn() === $originalEventOpen, 'Could not restore the original event state.');
		} catch (Throwable $e) {
			fwrite(STDERR, "Test event-state cleanup failed; inspect only the isolated test database.\n");
			$exitCode = 1;
		}
	}
	if ($db instanceof PDO && $testDataPrepared) {
		try {
			if ($registeredMemberId === null && isset($username)) {
				$lookup = $db->prepare('SELECT memberID FROM members WHERE username = :username');
				$lookup->execute(array(':username' => $username));
				$registeredMemberId = $lookup->fetchColumn();
				if ($registeredMemberId) {
					$memberIds[] = (int) $registeredMemberId;
				}
			}
			if ($memberIds) {
				$ids = implode(',', array_map('intval', array_unique($memberIds)));
				$db->exec('DELETE FROM answerlog WHERE memberID IN (' . $ids . ')');
				$db->exec('DELETE FROM members WHERE memberID IN (' . $ids . ')');
			}
			$db->exec('DELETE FROM request_limits');
			if ($questionTouched) {
				$db->exec('DELETE FROM questions WHERE qID = 0');
			}
			if ($questionLevelOneTouched) {
				$db->exec('DELETE FROM questions WHERE qID = 1');
			}
		} catch (Throwable $e) {
			fwrite(STDERR, "Test database cleanup failed; inspect only the isolated test database.\n");
			$exitCode = 1;
		}
	}
	@unlink($capturePath);
	foreach (glob($sessionDirectory . '/*') as $sessionFile) {
		if (is_file($sessionFile)) {
			@unlink($sessionFile);
		}
	}
	@rmdir($sessionDirectory);
	@rmdir($runtimeDirectory);
}

exit($exitCode);
