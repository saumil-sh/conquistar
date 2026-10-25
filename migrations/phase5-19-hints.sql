-- Apply after phase4-18-quiz-master-role.sql on an existing database.
ALTER TABLE questions
	ADD hint TEXT NULL;

ALTER TABLE members
	ADD hints_used INT(11) NOT NULL DEFAULT 0;

CREATE TABLE hint_uses (
	memberID INT(11) NOT NULL,
	qID INT(11) NOT NULL,
	used_at DATETIME(6) NOT NULL,
	PRIMARY KEY (memberID, qID)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
