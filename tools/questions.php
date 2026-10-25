<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once(__DIR__ . '/../php/includes/question-format.php');
$mediaCatalog = require(__DIR__ . '/../php/includes/media-catalog.php');

function question_path_is_inside($path, $root)
{
	return $path === $root || strpos($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
}

function question_authoring_dirs()
{
	$contentSetting = getenv('QUESTION_CONTENT_DIR');
	$webRootSetting = getenv('QUESTION_WEB_ROOT');
	if (!is_string($contentSetting) || $contentSetting === '' || !is_string($webRootSetting) || $webRootSetting === '') {
		throw new RuntimeException('Set QUESTION_CONTENT_DIR and QUESTION_WEB_ROOT to existing directories.');
	}
	$contentRoot = realpath($contentSetting);
	$webRoot = realpath($webRootSetting);
	$repositoryRoot = realpath(__DIR__ . '/..');
	if ($contentRoot === false || $webRoot === false || $repositoryRoot === false
		|| !is_dir($contentRoot) || !is_dir($webRoot)) {
		throw new RuntimeException('Set QUESTION_CONTENT_DIR and QUESTION_WEB_ROOT to existing directories.');
	}
	if (question_path_is_inside($contentRoot, $repositoryRoot)
		|| question_path_is_inside($contentRoot, $webRoot)) {
		throw new RuntimeException('Question files must be stored outside the repository and web document root.');
	}

	$directories = array();
	foreach (array('drafts', 'published') as $name) {
		$path = realpath($contentRoot . DIRECTORY_SEPARATOR . $name);
		$permissions = $path === false ? false : fileperms($path);
		if ($path === false || !is_dir($path) || !question_path_is_inside($path, $contentRoot)
			|| $permissions === false || ($permissions & 0077) !== 0) {
			throw new RuntimeException('Create private drafts and published directories under QUESTION_CONTENT_DIR.');
		}
		$directories[$name] = $path;
	}
	$directories['live'] = $webRoot . DIRECTORY_SEPARATOR . 'questions';
	if (!is_dir($directories['live'])) {
		throw new RuntimeException('QUESTION_WEB_ROOT must contain a questions directory.');
	}
	$rootPermissions = fileperms($contentRoot);
	if ($rootPermissions === false || ($rootPermissions & 0077) !== 0) {
		throw new RuntimeException('QUESTION_CONTENT_DIR must be private to its owner.');
	}
	return $directories;
}

if (!in_array($argv[1] ?? '', array('validate', 'preview', 'publish'), true)
	|| $argc !== ($argv[1] === 'publish' ? 4 : 3)
	|| !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $argv[2])
	|| ($argc === 4 && !preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D', $argv[3]))) {
	fwrite(STDERR, "Usage: php tools/questions.php validate|preview <question-id>\n");
	fwrite(STDERR, "       php tools/questions.php publish <question-id> <level>\n");
	exit(2);
}

try {
	$directories = question_authoring_dirs();
	$id = $argv[2];
	$entry = find_question_file($id, $directories, $mediaCatalog);

	if ($argv[1] === 'validate') {
		echo "Question " . $id . " is valid.\n";
	} elseif ($argv[1] === 'preview') {
		echo question_preview_text($entry['question']);
	} elseif ($entry['status'] !== 'draft') {
		throw new RuntimeException('Only draft questions can be published.');
	} else {
		$level = (int) $argv[3];
		$page = $directories['live'] . DIRECTORY_SEPARATOR . 'q_' . $level . '.html';
		$handle = @fopen($page, 'x');
		if ($handle === false || fwrite($handle, question_body_html($entry['question'])) === false || !fclose($handle)) {
			throw new RuntimeException('Could not write ' . $page . '; a page for that level may already exist.');
		}
		try {
			publish_question_file($entry['path'], $directories['published'] . DIRECTORY_SEPARATOR . $id . '.md');
		} catch (Throwable $e) {
			unlink($page);
			throw $e;
		}
		$sqlText = function ($value) {
			return $value === null ? 'NULL' : "'" . str_replace(array('\\', "'"), array('\\\\', "''"), $value) . "'";
		};
		echo "Wrote " . $page . ". Now run this SQL on the game database:\n";
		echo "INSERT INTO questions (qID, Answer, hint) VALUES (" . $level . ", "
			. $sqlText(password_hash(question_answer_key($entry['question']['answer']), PASSWORD_DEFAULT)) . ", "
			. $sqlText($entry['question']['hint'] ?? null) . ");\n";
	}
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}
