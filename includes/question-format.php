<?php

function parse_question_markdown($source, array $mediaCatalog)
{
	if (!is_string($source) || strlen($source) > 25000 || strpos($source, "\0") !== false) {
		throw new InvalidArgumentException('Question file is too large or invalid.');
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

	$fields['body'] = $body;
	return $fields;
}
