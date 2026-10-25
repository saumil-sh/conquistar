# Conquistar

A small online puzzle hunt. Players register, solve one question per level,
can reveal one hint per question, and race up a leaderboard. You, the quiz
master, add the questions and open or close the event.

Developing or testing the code? See [CONTRIBUTING.md](CONTRIBUTING.md).

## Self-hosting

You need PHP 8.2 or newer with `pdo_mysql`, MariaDB or MySQL, and a way to send
email. Shared hosting with cPanel usually has all three.

1. Upload the repository and point the domain's document root at `php/`.
   In cPanel, set it under Domains. Everything outside `php/` then stays private.
2. Create an empty database and a database user, then load the schema:

   ```sh
   mariadb -u USER -p DBNAME < schema.sql
   ```

3. Set these environment variables for PHP (the app does not read `.env` files):

   | Variable | Example | Purpose |
   |---|---|---|
   | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `localhost`, `3306`, … | Database connection |
   | `APP_URL` | `https://example.org/` | Base URL used in email links. Do not derive it from the request. |
   | `SITE_EMAIL` | `quiz@example.org` | Sender address. Without it, no email is sent. |
   | `SITE_NAME` | `My Hunt` | Name in the menu, titles and emails (default `Conquistar`) |
   | `HINTS_URL` | `https://…` | Optional link to an external hints page |

4. Make sure PHP can send email. Email uses PHP's `mail()`, which needs a
   working `sendmail`. Shared hosting usually provides one. Elsewhere, install
   `msmtp` and `msmtp-mta` and put your SMTP account in `~/.msmtprc`
   (`chmod 600`).
5. Apply the PHP settings in `php/php.ini` (in cPanel: MultiPHP INI Editor) and
   use a patched PHP release. Details are in
   [CONTRIBUTING.md](CONTRIBUTING.md#production-php-settings).
6. Register your own account on the site, then make it the quiz master. The
   site never grants this role itself:

   ```sql
   UPDATE members SET quiz_master = 1 WHERE username = 'your-name';
   ```

7. Add questions (next section), then open the event on `quizmaster.php`.
   The event starts closed, and players cannot answer until you open it.

## Adding a question

Players see level `N` from `php/questions/q_N.html` (levels start at 0). Store
its answer as a hash of the lowercase answer without spaces, with an optional
hint that players can reveal once:

```sh
php -r 'echo password_hash("jupiter", PASSWORD_DEFAULT), PHP_EOL;'
```

```sql
INSERT INTO questions (qID, Answer, hint) VALUES (0, '<hash>', 'Named after a Roman god.');
```

Each revealed hint is recorded in `hint_uses` with a UTC time and counted in
`members.hints_used`. Question pages are ignored by git, so your content stays
out of the repository.

### Images

Question media is served by `php/media.php` from an explicit file map and is
available only at or below the player's current level. Put media files in
`php/accessible/` and list them in `php/includes/media-catalog.php`. Keep the
web-server access rules in `php/accessible/.htaccess` and
`php/questions/.htaccess` enabled; if a front-end server ignores Apache rules,
deny direct access to both directories there as well. If the repository root
is ever served instead of `php/`, the `tests/.htaccess` and `tools/.htaccess`
rules deny direct web access to those folders.

## How the game works

All times are stored and shown in UTC.

The leaderboard includes active accounts only. It orders by highest level,
then fewest hints used, then earliest solve time, then lowest member ID for a deterministic final tie.
It shows the first 50 players and, when you are signed in, your rank even if
you are outside that list.

Players can delete their account from the dashboard. The deletion request is
CSRF-protected, requires the signed-in session, and removes the member and
answer history in one transaction.
