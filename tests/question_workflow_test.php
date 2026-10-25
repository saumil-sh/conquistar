<?php

function question_workflow_check($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function question_workflow_run($action, $id, $contentRoot, $webRoot)
{
	$command = array(PHP_BINARY, __DIR__ . '/../tools/questions.php', $action, $id);
	$process = proc_open($command, array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	), $pipes, dirname(__DIR__), array(
		'QUESTION_CONTENT_DIR' => $contentRoot,
		'QUESTION_WEB_ROOT' => $webRoot,
	));
	question_workflow_check(is_resource($process), 'Could not run the question authoring command.');
	$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array('status' => proc_close($process), 'output' => $output);
}

function question_workflow_remove($path)
{
	if (is_link($path)) {
		unlink($path);
		return;
	}
	if (!is_dir($path)) {
		if (file_exists($path) || is_link($path)) {
			unlink($path);
		}
		return;
	}
	foreach (scandir($path) as $name) {
		if ($name !== '.' && $name !== '..') {
			question_workflow_remove($path . DIRECTORY_SEPARATOR . $name);
		}
	}
	rmdir($path);
}

$runtimeBase = __DIR__ . '/.runtime';
$createdRuntimeBase = false;
if (!is_dir($runtimeBase)) {
	if (!mkdir($runtimeBase, 0700)) {
		fwrite(STDERR, "Could not create question test workspace.\n");
		exit(1);
	}
	$createdRuntimeBase = true;
}
$workspace = $runtimeBase . '/questions-' . bin2hex(random_bytes(5));
$contentRoot = $workspace . '/content';
$webRoot = $workspace . '/web';
$draftDirectory = $contentRoot . '/drafts';
$publishedDirectory = $contentRoot . '/published';
$source = "---\nid: sample-lunar\ntitle: Sample clue\nanswer: Example answer\nhint: First hint\nmedia: fb\n---\n# Sample clue\nFind the answer.\n![Example image](media:fb)\n";
$exitCode = 0;

try {
	foreach (array($draftDirectory, $publishedDirectory, $webRoot) as $directory) {
		mkdir($directory, 0700, true);
	}
	file_put_contents($draftDirectory . '/sample-lunar.md', $source);

	$preview = question_workflow_run('preview', 'sample-lunar', $contentRoot, $webRoot);
	question_workflow_check($preview['status'] === 0
		&& strpos($preview['output'], 'Example answer') !== false
		&& strpos($preview['output'], 'First hint') !== false
		&& strpos($preview['output'], 'Find the answer.') !== false, 'Quiz-master preview omitted required content.');

	$published = question_workflow_run('publish', 'sample-lunar', $contentRoot, $webRoot);
	$publishedFile = $publishedDirectory . '/sample-lunar.md';
	question_workflow_check($published['status'] === 0
		&& !file_exists($draftDirectory . '/sample-lunar.md')
		&& is_file($publishedFile)
		&& strpos(file_get_contents($publishedFile), 'Example answer') !== false, 'Valid question was not safely published.');
	question_workflow_check(strpos(realpath($publishedDirectory), realpath($webRoot) . DIRECTORY_SEPARATOR) !== 0, 'Published question landed under the web root.');

	file_put_contents($draftDirectory . '/sample-lunar.md', $source);
	$duplicate = question_workflow_run('publish', 'sample-lunar', $contentRoot, $webRoot);
	question_workflow_check($duplicate['status'] !== 0
		&& is_file($draftDirectory . '/sample-lunar.md')
		&& file_get_contents($publishedFile) === $source, 'Duplicate publication overwrote published content.');

	$invalidSource = str_replace("answer: Example answer\n", '', $source);
	file_put_contents($draftDirectory . '/invalid-question.md', str_replace('sample-lunar', 'invalid-question', $invalidSource));
	$invalid = question_workflow_run('validate', 'invalid-question', $contentRoot, $webRoot);
	question_workflow_check($invalid['status'] !== 0, 'Invalid question content was accepted.');

	$webContent = $webRoot . '/private';
	mkdir($webContent . '/drafts', 0700, true);
	mkdir($webContent . '/published', 0700, true);
	file_put_contents($webContent . '/drafts/sample-lunar.md', $source);
	$publicStorage = question_workflow_run('preview', 'sample-lunar', $webContent, $webRoot);
	question_workflow_check($publicStorage['status'] !== 0, 'Question content inside the web root was accepted.');
	echo "Question preview and publication checks passed.\n";
} catch (Throwable $e) {
	fwrite(STDERR, "Question workflow test failed: " . $e->getMessage() . "\n");
	$exitCode = 1;
} finally {
	question_workflow_remove($workspace);
	if ($createdRuntimeBase) {
		@rmdir($runtimeBase);
	}
}

exit($exitCode);
