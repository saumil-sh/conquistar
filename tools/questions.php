<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once(__DIR__ . '/../includes/question-format.php');
$mediaCatalog = require(__DIR__ . '/../includes/media-catalog.php');

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
	if ($contentRoot === false || $webRoot === false || !is_dir($contentRoot) || !is_dir($webRoot)) {
		throw new RuntimeException('Set QUESTION_CONTENT_DIR and QUESTION_WEB_ROOT to existing directories.');
	}
	if (question_path_is_inside($contentRoot, $webRoot)) {
		throw new RuntimeException('Question files must be stored outside the web document root.');
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
	$rootPermissions = fileperms($contentRoot);
	if ($rootPermissions === false || ($rootPermissions & 0077) !== 0) {
		throw new RuntimeException('QUESTION_CONTENT_DIR must be private to its owner.');
	}
	return $directories;
}

function question_authoring_files(array $directories, array $mediaCatalog)
{
	$questions = array();
	foreach ($directories as $status => $directory) {
		foreach (glob($directory . DIRECTORY_SEPARATOR . '*.md') ?: array() as $path) {
			if (is_link($path) || !is_file($path)) {
				throw new RuntimeException('Question files cannot be links or special files.');
			}
			$size = filesize($path);
			if ($size === false || $size > 25000) {
				throw new RuntimeException('Question file is too large.');
			}
			$contents = file_get_contents($path);
			if ($contents === false) {
				throw new RuntimeException('Could not read a question file.');
			}
			$question = parse_question_markdown($contents, $mediaCatalog);
			if (basename($path) !== $question['id'] . '.md' || isset($questions[$question['id']])) {
				throw new RuntimeException('Question IDs must be unique and match their filenames.');
			}
			$questions[$question['id']] = array(
				'status' => $status,
				'path' => $path,
				'question' => $question,
			);
		}
	}
	return $questions;
}

function question_preview(array $question)
{
	echo "Quiz-master preview (private)\n";
	echo "ID: " . $question['id'] . "\n";
	echo "Title: " . $question['title'] . "\n\n";
	echo $question['body'] . "\n\n";
	echo "Hints:\n";
	foreach ($question['hints'] as $hint) {
		echo "- " . $hint . "\n";
	}
	echo "Answer: " . $question['answer'] . "\n";
	echo "Media: " . ($question['media'] ? implode(', ', $question['media']) : 'none') . "\n";
}

if ($argc !== 3 || !in_array($argv[1], array('validate', 'preview', 'publish'), true)
	|| !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $argv[2])) {
	fwrite(STDERR, "Usage: php tools/questions.php validate|preview|publish <question-id>\n");
	exit(2);
}

try {
	$directories = question_authoring_dirs();
	$questions = question_authoring_files($directories, $mediaCatalog);
	$id = $argv[2];
	if (!isset($questions[$id])) {
		throw new RuntimeException('Question ID was not found in drafts or published content.');
	}
	$entry = $questions[$id];

	if ($argv[1] === 'validate') {
		echo "Question " . $id . " is valid.\n";
	} elseif ($argv[1] === 'preview') {
		question_preview($entry['question']);
	} elseif ($entry['status'] !== 'draft') {
		throw new RuntimeException('Only draft questions can be published.');
	} else {
		$destination = $directories['published'] . DIRECTORY_SEPARATOR . $id . '.md';
		if (file_exists($destination) || is_link($destination) || !@link($entry['path'], $destination)) {
			throw new RuntimeException('Could not publish; the destination exists or safe publication is unavailable.');
		}
		if (!@unlink($entry['path'])) {
			@unlink($destination);
			throw new RuntimeException('Could not complete publication; the draft was retained.');
		}
		echo "Question " . $id . " was published to the private release queue.\n";
	}
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}
