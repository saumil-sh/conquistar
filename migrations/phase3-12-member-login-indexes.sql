-- Review the members schema and backup before applying.
-- Confirm logincount and both named indexes are absent. Check for duplicate
-- usernames/emails before adding the unique keys; resolve duplicates manually.
ALTER TABLE members ADD logincount INT(11) NOT NULL DEFAULT 0;

ALTER TABLE members ADD UNIQUE KEY members_username (username);

ALTER TABLE members ADD UNIQUE KEY members_email (email);
