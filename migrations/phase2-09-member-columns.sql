-- Apply only after verifying memberID is a non-null unique/primary key and backing up the database.
UPDATE members SET level = 0 WHERE level IS NULL;

ALTER TABLE members
	MODIFY memberID INT(11) NOT NULL AUTO_INCREMENT,
	MODIFY active VARCHAR(64) NOT NULL,
	MODIFY resetToken VARCHAR(64) DEFAULT NULL,
	MODIFY level INT(11) NOT NULL DEFAULT 0;
