CREATE TABLE event_state (
	singleton_id TINYINT UNSIGNED NOT NULL,
	is_open TINYINT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (singleton_id)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

INSERT INTO event_state (singleton_id, is_open) VALUES (1, 0);
