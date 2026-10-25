-- Review the live members schema before applying; pending links get one final one-hour window.
ALTER TABLE members ADD resetExpiresAt DATETIME NULL;

UPDATE members
SET resetExpiresAt = DATE_ADD(NOW(), INTERVAL 1 HOUR)
WHERE resetComplete = 'No' AND resetToken IS NOT NULL AND resetToken <> '';
