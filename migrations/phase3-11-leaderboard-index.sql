-- Apply after phase2-09-member-columns.sql on an existing database.
ALTER TABLE members
	ADD INDEX members_leaderboard (active, level, lastsolvetime, memberID);
