<?php
require_once(__DIR__ . '/../includes/question-format.php');
$mediaCatalog = require(__DIR__ . '/../includes/media-catalog.php');

function question_authoring_check($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function question_authoring_remove($path)
{
	if (is_link($path)) {
		unlink($path);
		return;
	}
	if (!is_dir($path)) {
		if (file_exists($path)) {
			unlink($path);
		}
		return;
	}
	foreach (scandir($path) as $name) {
		if ($name !== '.' && $name !== '..') {
			question_authoring_remove($path . DIRECTORY_SEPARATOR . $name);
		}
	}
	rmdir($path);
}

function question_authoring_command($contentRoot, $webRoot)
{
	$process = proc_open(array(PHP_BINARY, __DIR__ . '/../tools/questions.php', 'validate', 'sample-lunar'), array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	), $pipes, dirname(__DIR__), array(
		'QUESTION_CONTENT_DIR' => $contentRoot,
		'QUESTION_WEB_ROOT' => $webRoot,
	));
	question_authoring_check(is_resource($process), 'Could not start question validation command.');
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array('status' => proc_close($process), 'output' => $output);
}

$valid = "---\nid: sample-lunar\ntitle: Sample clue\nanswer: Example answer\nhint: First hint\nhint: Second hint\nmedia: fb\n---\n# Sample clue\nFind the answer.\n\n![An example image](media:fb)\n[Safe link](https://example.test/path)\n";
$valid = str_replace("media: fb\n", '', $valid);
$valid = str_replace("\n\n![An example image](media:fb)", '', $valid);
$runtimeBase = __DIR__ . '/.runtime';
$createdRuntimeBase = false;
if (!is_dir($runtimeBase)) {
	if (!mkdir($runtimeBase, 0700)) {
		fwrite(STDERR, "Could not create question test workspace.\n");
		exit(1);
	}
	$createdRuntimeBase = true;
}
$workspace = $runtimeBase . '/authoring-' . bin2hex(random_bytes(5));
$draftDirectory = $workspace . '/drafts';
$publishedDirectory = $workspace . '/published';
$directories = array('drafts' => $draftDirectory, 'published' => $publishedDirectory);
$exitCode = 0;

try {
	mkdir($draftDirectory, 0700, true);
	mkdir($publishedDirectory, 0700, true);
	$question = parse_question_markdown($valid, $mediaCatalog);
	$bomQuestion = parse_question_markdown("\xEF\xBB\xBF" . $valid, $mediaCatalog);
	question_authoring_check($question['id'] === 'sample-lunar'
		&& count($question['hints']) === 2
		&& $question['media'] === array()
		&& $bomQuestion['id'] === $question['id'], 'Valid Markdown or BOM-prefixed question was not parsed.');

	$testMediaCatalog = array('test-image' => array('images/test.png', 0, 'image/png'));
	$mediaQuestionSource = str_replace(
		"hint: Second hint\n",
		"hint: Second hint\nmedia: test-image\n",
		str_replace(
			'Find the answer.',
			'Find the answer. ![An example image](media:test-image)',
			$valid
		)
	);
	$mediaQuestion = parse_question_markdown($mediaQuestionSource, $testMediaCatalog);
	question_authoring_check($mediaQuestion['media'] === array('test-image'), 'Approved media ID was not parsed.');

	$invalid = array(
		str_replace("answer: Example answer\n", '', $valid),
		str_replace('id: sample-lunar', 'id: ../sample', $valid),
		str_replace("hint: Second hint\n", "hint: Second hint\nmedia: unknown\n", $valid),
		str_replace('Find the answer.', '![Unknown image](media:unknown)', $valid),
		str_replace('# Sample clue', '<script>alert(1)</script>', $valid),
		str_replace('Find the answer.', '![External image][remote]', $valid) . "\n[remote]: https://example.test/image.png\n",
		str_replace('[Safe link](https://example.test/path)', '[Unsafe link](javascript:alert(1))', $valid),
		str_replace('[Safe link](https://example.test/path)', '[Unsafe link](data:text/html,hello)', $valid),
		str_replace('[Safe link](https://example.test/path)', "[remote]: javascript:alert(1)\n", $valid),
		$valid . str_repeat('x', 25001),
	);
	foreach ($invalid as $source) {
		try {
			parse_question_markdown($source, $mediaCatalog);
			throw new RuntimeException('Invalid Markdown question was accepted.');
		} catch (InvalidArgumentException $e) {
		}
	}

	$draftPath = $draftDirectory . '/sample-lunar.md';
	$publishedPath = $publishedDirectory . '/sample-lunar.md';
	file_put_contents($draftPath, $valid);
	file_put_contents($draftDirectory . '/unrelated-bad-draft.md', 'not valid Markdown');
	$entry = find_question_file('sample-lunar', $directories, $mediaCatalog);
	$preview = question_preview_text($entry['question']);
	question_authoring_check(strpos($preview, 'Example answer') !== false
		&& strpos($preview, 'First hint') !== false
		&& strpos($preview, 'Find the answer.') !== false, 'Quiz-master preview omitted required content.');

	$invalidPath = $draftDirectory . '/broken.md';
	file_put_contents($invalidPath, str_replace("answer: Example answer\n", '', $valid));
	try {
		question_parse_file($invalidPath, $mediaCatalog);
		throw new RuntimeException('Invalid question file was accepted.');
	} catch (InvalidArgumentException $e) {
		question_authoring_check(strpos($e->getMessage(), 'broken.md') !== false, 'Parser error omitted its filename.');
	}

	publish_question_file($entry['path'], $publishedPath);
	question_authoring_check(!file_exists($draftPath)
		&& file_get_contents($publishedPath) === $valid, 'Valid question was not safely published.');
	file_put_contents($draftPath, $valid);
	try {
		publish_question_file($draftPath, $publishedPath);
		throw new RuntimeException('Duplicate publication overwrote published content.');
	} catch (RuntimeException $e) {
		question_authoring_check(is_file($draftPath) && file_get_contents($publishedPath) === $valid, 'Failed publication changed question content.');
	}

	try {
		find_question_file('sample-lunar', $directories, $mediaCatalog);
		throw new RuntimeException('Duplicate question ID was accepted.');
	} catch (RuntimeException $e) {
		question_authoring_check(strpos($e->getMessage(), $draftPath) !== false
			&& strpos($e->getMessage(), $publishedPath) !== false, 'Duplicate-ID error omitted filenames.');
	}

	$fakeWebRoot = $workspace . '/fake-web';
	mkdir($fakeWebRoot, 0700);
	$insideRepository = question_authoring_command($workspace, $fakeWebRoot);
	question_authoring_check($insideRepository['status'] !== 0
		&& strpos($insideRepository['output'], 'outside the repository') !== false, 'Question storage inside the repository was accepted with a different web-root setting.');
	echo "Question authoring checks passed.\n";
} catch (Throwable $e) {
	fwrite(STDERR, "Question authoring test failed: " . $e->getMessage() . "\n");
	$exitCode = 1;
} finally {
	question_authoring_remove($workspace);
	if ($createdRuntimeBase) {
		@rmdir($runtimeBase);
	}
}

exit($exitCode);
