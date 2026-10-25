<?php

function is_test_database($testName, $configuredName)
{
	return is_string($testName)
		&& preg_match('/\Atest(?:_[A-Za-z0-9]+)*\z/D', $testName) === 1
		&& is_string($configuredName)
		&& $configuredName === $testName;
}

function require_test_database_environment()
{
	$testName = getenv('TEST_DB_NAME');
	$configuredName = getenv('DB_NAME');
	if (!is_test_database($testName, $configuredName)) {
		fwrite(STDERR, "Refusing database tests: set DB_NAME and TEST_DB_NAME to the same test or test_* database.\n");
		exit(2);
	}
	return $testName;
}
