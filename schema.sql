-- Database schema for a fresh install. Do not run on a database that already has these tables.

CREATE TABLE members (
	memberID INT(11) NOT NULL AUTO_INCREMENT,
	username VARCHAR(191) NOT NULL,
	password VARCHAR(255) NOT NULL,
	email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
	active VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	activationExpiresAt DATETIME DEFAULT NULL,
	resetToken VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
	resetComplete CHAR(3) NOT NULL DEFAULT 'No',
	resetExpiresAt DATETIME DEFAULT NULL,
	level INT(11) NOT NULL DEFAULT 0,
	ip VARCHAR(45) NOT NULL,
	institute VARCHAR(100) NOT NULL,
	lastsolvetime DATETIME(6) NOT NULL,
	logincount INT(11) NOT NULL DEFAULT 0,
	hints_used INT(11) NOT NULL DEFAULT 0,
	quiz_master TINYINT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (memberID),
	UNIQUE KEY members_username (username),
	UNIQUE KEY members_email (email),
	KEY members_leaderboard (active, level, hints_used, lastsolvetime, memberID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
	qID INT(11) NOT NULL,
	Answer VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	hint TEXT NULL,
	PRIMARY KEY (qID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hint_uses (
	memberID INT(11) NOT NULL,
	qID INT(11) NOT NULL,
	used_at DATETIME(6) NOT NULL,
	PRIMARY KEY (memberID, qID)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

CREATE TABLE answerlog (
	logID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	memberID INT(11) NOT NULL,
	answer TEXT NOT NULL,
	TimeOfAnswer DATETIME NOT NULL,
	ip VARCHAR(45) NOT NULL,
	PRIMARY KEY (logID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_limits (
	rate_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	window_started_at BIGINT UNSIGNED NOT NULL,
	request_count INT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (rate_key),
	KEY request_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

CREATE TABLE event_state (
	singleton_id TINYINT UNSIGNED NOT NULL,
	is_open TINYINT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (singleton_id)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

INSERT INTO event_state (singleton_id, is_open) VALUES (1, 0);
