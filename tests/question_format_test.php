<?php
require_once(__DIR__ . '/../includes/question-format.php');
$mediaCatalog = require(__DIR__ . '/../includes/media-catalog.php');

$valid = "---\nid: sample-lunar\ntitle: Sample clue\nanswer: Example answer\nhint: First hint\nhint: Second hint\nmedia: fb\n---\n# Sample clue\nFind the answer.\n\n![An example image](media:fb)\n";
$question = parse_question_markdown($valid, $mediaCatalog);
if ($question['id'] !== 'sample-lunar'
	|| count($question['hints']) !== 2
	|| $question['media'] !== array('fb')
	|| strpos($question['body'], '![An example image](media:fb)') === false) {
	fwrite(STDERR, "Valid Markdown question was not parsed as expected.\n");
	exit(1);
}

$invalid = array(
	str_replace("answer: Example answer\n", '', $valid),
	str_replace('id: sample-lunar', 'id: ../sample', $valid),
	str_replace('media: fb', 'media: /etc/passwd', $valid),
	str_replace('media:fb', 'media:unknown', $valid),
	str_replace('# Sample clue', '<script>alert(1)</script>', $valid),
	$valid . str_repeat('x', 25001),
);
foreach ($invalid as $source) {
	try {
		parse_question_markdown($source, $mediaCatalog);
	} catch (InvalidArgumentException $e) {
		continue;
	}
	fwrite(STDERR, "Invalid Markdown question was accepted.\n");
	exit(1);
}

echo "Question format validation passed.\n";
