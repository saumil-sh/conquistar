<?php

function parse_question_markdown($source, array $mediaCatalog)
{
	if (!is_string($source) || strlen($source) > 25000 || strpos($source, "\0") !== false) {
		throw new InvalidArgumentException('Question file is too large or invalid.');
	}
	if (substr($source, 0, 3) === "\xEF\xBB\xBF") {
		$source = substr($source, 3);
	}
	$source = str_replace(array("\r\n", "\r"), "\n", $source);
	if (!preg_match('/\A---\n(.*?)\n---\n(.+)\z/s', $source, $matches)) {
		throw new InvalidArgumentException('Question needs a metadata block and Markdown body.');
	}

	$fields = array('hints' => array(), 'media' => array());
	foreach (explode("\n", $matches[1]) as $line) {
		if ($line === '') {
			continue;
		}
		if (!preg_match('/\A(id|title|answer|hint|media):[ \t]*(.*)\z/D', $line, $field)) {
			throw new InvalidArgumentException('Unknown or malformed question metadata.');
		}
		$key = $field[1];
		$value = trim($field[2]);
		if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
			throw new InvalidArgumentException('Question metadata cannot contain control characters.');
		}
		if ($key === 'hint') {
			if ($value === '' || strlen($value) > 500) {
				throw new InvalidArgumentException('Each hint must contain 1 to 500 characters.');
			}
			$fields['hints'][] = $value;
			continue;
		}
		if ($key === 'media') {
			if ($value === '' || !isset($mediaCatalog[$value]) || in_array($value, $fields['media'], true)) {
				throw new InvalidArgumentException('Media must use a unique approved media ID.');
			}
			$fields['media'][] = $value;
			continue;
		}
		if (isset($fields[$key])) {
			throw new InvalidArgumentException('Question metadata fields may appear only once.');
		}
		$fields[$key] = $value;
	}

	if (!isset($fields['id'], $fields['title'], $fields['answer'])
		|| !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $fields['id'])
		|| strlen($fields['id']) > 64
		|| $fields['title'] === ''
		|| strlen($fields['title']) > 160
		|| $fields['answer'] === ''
		|| strlen($fields['answer']) > 255
		|| !$fields['hints']) {
		throw new InvalidArgumentException('Question requires a stable ID, title, answer, and at least one hint.');
	}

	$body = trim($matches[2]);
	if ($body === '' || preg_match('/<\/?[A-Za-z][^>]*>/', $body)) {
		throw new InvalidArgumentException('Question body must be non-empty Markdown without HTML.');
	}

	if (preg_match_all('/!\[[^\]\n]*\]\(media:([^)]+)\)/', $body, $references)) {
		foreach ($references[1] as $mediaId) {
			if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $mediaId)
				|| !in_array($mediaId, $fields['media'], true)) {
				throw new InvalidArgumentException('Question media references must be declared by approved ID.');
			}
		}
	}
	if (preg_match('/!\[[^\]\n]*\]\((?!media:)[^)]+\)/', $body)) {
		throw new InvalidArgumentException('Images must use an approved media ID.');
	}
	$bodyWithoutMedia = preg_replace('/!\[[^\]\n]*\]\(media:[a-z0-9]+(?:-[a-z0-9]+)*\)/', '', $body);
	if (strpos($bodyWithoutMedia, '![') !== false
		|| preg_match('/^\s{0,3}\[[^\]\n]+\]:/m', $body)
		|| preg_match('/(?<!\!)\[[^\]\n]+\]\s*\[[^\]\n]*\]/', $body)) {
		throw new InvalidArgumentException('Reference-style Markdown images and links are not supported.');
	}
	if (preg_match_all('/(?<!\!)\[[^\]\n]+\]\(([^)\n]*)\)/', $body, $links)) {
		foreach ($links[1] as $target) {
			if (!preg_match('/\A(?:https?:\/\/[^\s<>]+|mailto:[^\s<>]+|#[A-Za-z0-9_-]+)\z/i', $target)) {
				throw new InvalidArgumentException('Markdown links must use https, http, mailto, or a local fragment.');
			}
		}
	}

	$fields['body'] = $body;
	return $fields;
}

function question_parse_file($path, array $mediaCatalog)
{
	if (is_link($path) || !is_file($path)) {
		throw new RuntimeException($path . ': expected a regular question file.');
	}
	$size = filesize($path);
	if ($size === false || $size > 25000) {
		throw new RuntimeException($path . ': question file is too large.');
	}
	$source = file_get_contents($path);
	if ($source === false) {
		throw new RuntimeException($path . ': could not read question file.');
	}
	try {
		return parse_question_markdown($source, $mediaCatalog);
	} catch (InvalidArgumentException $e) {
		throw new InvalidArgumentException($path . ': ' . $e->getMessage());
	}
}

function find_question_file($id, array $directories, array $mediaCatalog)
{
	$draftPath = $directories['drafts'] . DIRECTORY_SEPARATOR . $id . '.md';
	$publishedPath = $directories['published'] . DIRECTORY_SEPARATOR . $id . '.md';
	$hasDraft = file_exists($draftPath) || is_link($draftPath);
	$hasPublished = file_exists($publishedPath) || is_link($publishedPath);
	if ($hasDraft && $hasPublished) {
		throw new RuntimeException('Duplicate question ID in ' . $draftPath . ' and ' . $publishedPath . '.');
	}
	if (!$hasDraft && !$hasPublished) {
		throw new RuntimeException('Question ID was not found in drafts or published content.');
	}

	$path = $hasDraft ? $draftPath : $publishedPath;
	$question = question_parse_file($path, $mediaCatalog);
	if ($question['id'] !== $id) {
		throw new RuntimeException($path . ': declared ID does not match the requested ID.');
	}
	return array(
		'status' => $hasDraft ? 'draft' : 'published',
		'path' => $path,
		'question' => $question,
	);
}

function question_preview_text(array $question)
{
	$preview = "Quiz-master preview (private)\n";
	$preview .= "ID: " . $question['id'] . "\n";
	$preview .= "Title: " . $question['title'] . "\n\n";
	$preview .= $question['body'] . "\n\nHints:\n";
	foreach ($question['hints'] as $hint) {
		$preview .= "- " . $hint . "\n";
	}
	$preview .= "Answer: " . $question['answer'] . "\n";
	$preview .= "Media: " . ($question['media'] ? implode(', ', $question['media']) : 'none') . "\n";
	return $preview;
}

function publish_question_file($source, $destination)
{
	if (file_exists($destination) || is_link($destination)
		|| is_link($source) || !is_file($source)
		|| !@link($source, $destination)) {
		throw new RuntimeException('Could not publish; the destination exists or safe publication is unavailable.');
	}
	if (!@unlink($source)) {
		@unlink($destination);
		throw new RuntimeException('Could not complete publication; the draft was retained.');
	}
}
