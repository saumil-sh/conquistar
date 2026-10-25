-- Run with the target database selected. This drops only the named index.
-- The conditional works on MySQL and MariaDB releases without DROP INDEX IF EXISTS.
SET @drop_answerlog_member_time = IF(
	(SELECT COUNT(*)
	 FROM information_schema.statistics
	 WHERE table_schema = DATABASE()
	   AND table_name = 'answerlog'
	   AND index_name = 'answerlog_member_time') = 0,
	'SELECT ''answerlog_member_time is absent; no change''',
	'ALTER TABLE answerlog DROP INDEX answerlog_member_time'
);

PREPARE phase3_drop_answerlog_member_time FROM @drop_answerlog_member_time;
EXECUTE phase3_drop_answerlog_member_time;
DEALLOCATE PREPARE phase3_drop_answerlog_member_time;
