CREATE TABLE request_limits (
	rate_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	window_started_at BIGINT UNSIGNED NOT NULL,
	request_count INT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (rate_key),
	KEY request_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
