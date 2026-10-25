<?php
require_once(__DIR__ . '/test_db_guard.php');

$cases = array(
	array('test', 'test', true),
	array('test_phase3', 'test_phase3', true),
	array('production', 'production', false),
	array('test_phase3', 'production', false),
	array('', 'test', false),
	array('test-name', 'test-name', false),
);

foreach ($cases as $case) {
	if (is_test_database($case[0], $case[1]) !== $case[2]) {
		fwrite(STDERR, "Database safety guard case failed.\n");
		exit(1);
	}
}

echo "Test database safety guard passed.\n";
