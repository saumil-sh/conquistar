# Conquistar

## TODO
- understand how it works
    1. try making a new user without any errors.

## Production PHP settings

The checked-in `php.ini` records the required account settings, but cPanel controls
the PHP runtime and may regenerate this file. In cPanel, select a patched PHP
release and apply these settings through the MultiPHP INI Editor (or have the
host apply them before request parsing). PHP 8.2.35, 8.3.36, 8.4.27, and 8.5.12
include the fixes for CVE-2026-109618 and CVE-2026-109619. PHP 8.1 and older
need a vendor-confirmed backport or an upgrade; this repository does not select
or upgrade the server runtime.

Keep `allow_url_include` and `display_errors` off, set
`session.upload_progress.enabled=Off`, and keep `post_max_size` at 8M because
the application has no upload flow. Apply the upload-progress setting in the
server/PHP configuration before request parsing and restart all PHP workers;
`.user.ini` or application-level `ini_set()` is not sufficient. These measures
reduce exposure but are not a substitute for the fixed PHP release. The app's
actual PHP version and cPanel settings must be verified on the server.

Set `APP_URL` to the trusted absolute base URL used in activation and password
reset emails (for example, `https://example.org/`). Do not derive it from the
incoming request host. `schema.sql` is the canonical schema for a fresh
database. It includes the final columns introduced by the Phase 2 migrations
and the leaderboard index, plus the member login counter and unique account
indexes; do not apply the upgrade migrations on top of it.

For an existing database, review the SQL scripts in `migrations/` in this order
against a verified backup; the application does not run them:

1. `phase2-06-activation-expiry.sql`
2. `phase2-07-reset-expiry.sql`
3. `phase2-08-request-limits.sql`
4. `phase2-09-member-columns.sql`
5. `phase3-11-leaderboard-index.sql`
6. `phase3-12-member-login-indexes.sql`
7. `phase3-13-drop-answerlog-member-time.sql`
8. `phase4-17-event-state.sql`
9. `phase4-18-quiz-master-role.sql`

Before step 4, verify `members.memberID` is a non-null unique/primary key and
that existing activation/reset tokens fit within 64 characters. The migration
sets null levels to zero before enforcing `NOT NULL DEFAULT 0`.
Before step 5, verify the index does not already exist. No live database schema
has been verified, and no migration has been run against a real database.
Before step 6, verify `logincount` and the named unique indexes are absent, and
check for duplicate usernames and emails; resolve duplicates manually before
adding either unique key. If any of these already exist, verify their
definitions and skip the corresponding addition rather than rerunning it. The
fresh schema omits `answerlog_member_time`; step 7 drops that named index only
if it exists, leaving every other index unchanged. The event begins closed in a
fresh schema and in step 8; a quiz master must open it before players can
continue.
Step 9 gives every existing account the non-privileged default. After verifying
the intended operator's account, grant quiz-master access directly to that
member ID with a controlled database change; the application never grants this
role through registration. Access is checked against the database on every
request.
Players can delete their account from the dashboard. The deletion request is
CSRF-protected, requires the signed-in session, and removes the member and
answer history in one transaction.

The canonical schema describes the tables used by the current application:
`members`, `questions`, `answerlog`, `request_limits`, and `event_state`. The
schema is the single fresh-install reference and does not drop or alter any
existing table or data.

Database tests require an explicitly named `test_*` database (bare `test` is
rejected) and matching `TEST_DB_NAME` and `DB_NAME`. The end-to-end runner also
requires a loopback database host. Set `DB_NAME` for the application
connection rather than editing the schema or migration files.

To run end-to-end checks, first create an isolated, empty `test_*` database and
apply `schema.sql` to it. Set `DB_NAME` and `TEST_DB_NAME` to that same test
database, set `TEST_DB_HOST=localhost`, and provide only test credentials
through `TEST_DB_USER` and `TEST_DB_PASSWORD`. Then run
`php tests/e2e.php`; it starts a local PHP server and captures test emails
privately rather than sending them. The runner refuses non-test names, remote
database hosts, and test databases with existing members, questions, answer
logs, or rate-limit rows.

The leaderboard includes active accounts only. It orders by highest level,
then earliest solve time, then lowest member ID for a deterministic final tie.
It shows the first 50 players and, when you are signed in, your rank even if
you are outside that list.

Sources: [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-5)
and [session upload-progress advisory](https://www.php.net/archive/2026.php#2026-10-23-1).

Question media is served by `media.php` from an explicit file map and is
available only at or below the player's current level. Keep the web-server
access rules in `accessible/.htaccess` and `questions/.htaccess` enabled; if a
front-end server ignores Apache rules, deny direct access to both directories
there as well. The `tests/.htaccess` and `tools/.htaccess` rules also deny
direct web access. Configure the production web server to deny `/tests/` and
`/tools/` too (for example, with matching Nginx `location` blocks), especially
if Apache overrides are disabled or the front-end server does not honor them.

## Authoring questions

New questions use a small Markdown file with a stable lowercase ID, title,
private answer, one or more hints, and optional approved media IDs:

```markdown
---
id: sample-lunar
title: Sample clue
answer: Example answer
hint: First example hint
hint: Second example hint
---
# Sample clue

Write the player-facing clue here.
```

Use one `id`, `title`, and `answer`; repeat `hint` or `media` as needed. Image
references must use an ID in `includes/media-catalog.php`; HTML, external or
reference-style images, and reference-style links are rejected. Inline links
allow only `https`, `http`, `mailto`, or local fragments. This format is
deliberately limited. Legacy question pages and their media have been moved to
the private `legacy-retired` archive under `QUESTION_CONTENT_DIR` and removed
from repository history. The player and quiz-master flows report that no
questions are available until new player-facing content is ready.

Keep authored files outside both the repository and the web document root. The
CLI enforces both boundaries; setting a different `QUESTION_WEB_ROOT` cannot
make an in-repository folder private. Set
`QUESTION_CONTENT_DIR` to a private directory with `drafts/` and `published/`
subdirectories, and set `QUESTION_WEB_ROOT` to the actual document root. For
example:

```sh
export QUESTION_CONTENT_DIR="$HOME/conquistar-private"
export QUESTION_WEB_ROOT="/path/to/the/actual/document-root"
mkdir -p "$QUESTION_CONTENT_DIR/drafts" "$QUESTION_CONTENT_DIR/published"
chmod 700 "$QUESTION_CONTENT_DIR" "$QUESTION_CONTENT_DIR/drafts" "$QUESTION_CONTENT_DIR/published"
```

Save the example as `$QUESTION_CONTENT_DIR/drafts/sample-lunar.md`, then run:

```sh
php tools/questions.php validate sample-lunar
php tools/questions.php preview sample-lunar
php tools/questions.php publish sample-lunar
```

Preview is terminal-only and includes the answer and hints for the quiz master.
Publishing validates the file and moves it into the private release queue; it
does not yet make Markdown content available to players. No Markdown answers
or drafts are served publicly.

The database-independent checks are `php tests/test_db_guard_test.php` and
`php tests/question_authoring_test.php`. The end-to-end suite is separate because
it requires the isolated local test database described above.
