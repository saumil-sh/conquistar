-- Apply after phase5-19-hints.sql on an existing database.
ALTER TABLE members
	DROP INDEX members_leaderboard,
	ADD INDEX members_leaderboard (active, level, hints_used, lastsolvetime, memberID);
