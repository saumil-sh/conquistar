-- Review the live members schema before applying; pending links get one final 24-hour window.
ALTER TABLE members ADD activationExpiresAt DATETIME NULL;

UPDATE members
SET activationExpiresAt = DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE active <> 'Yes' AND active <> '';
