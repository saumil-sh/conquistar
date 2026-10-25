# Contributing

This file is for people who work on the code. Running a hunt? See the
[README](README.md).

## Local development

The site lives in `php/`, which is the web root. Create a local database from
`schema.sql`, make sure the database server is running, then start the site
with your database credentials:

```sh
DB_HOST=127.0.0.1 DB_NAME=conquistar DB_USER=your_user DB_PASSWORD=your_password \
  php -S localhost:8000 -t php
```

Use `DB_PORT` if MySQL listens on a port other than 3306. The database and its
credentials must already exist; the application does not create them.

PHP does not load `.env` files automatically. If your credentials are saved in a
private shell file, load it before starting (or restarting) the PHP server:

```sh
. "$HOME/.config/conquistar/dev.env"
php -S localhost:8000 -t php
```

Keep that file outside the repository and web root, readable only by your user.
It must export the `DB_*` variables shown above.

With PHP's built-in server bound to `localhost`, `127.0.0.1`, or `::1`, an unset
`APP_URL` defaults to that server's loopback address and listening port.
Activation and reset links never use the incoming `Host` header. Set `APP_URL`
explicitly when testing another base URL or serving from a subdirectory.
Other server types and non-loopback bindings still require `APP_URL`.

## Tests

Database tests require an explicitly named `test_*` database (bare `test` is
rejected) and matching `TEST_DB_NAME` and `DB_NAME`. The end-to-end runner also
requires a loopback database host. Set `DB_NAME` for the application
connection rather than editing the schema file.

To run end-to-end checks, first create an isolated, empty `test_*` database and
apply `schema.sql` to it. Set `DB_NAME` and `TEST_DB_NAME` to that same test
database, set `TEST_DB_HOST=localhost`, and provide only test credentials
through `TEST_DB_USER` and `TEST_DB_PASSWORD`. Then run
`php tests/e2e.php`; it starts a local PHP server and captures test emails
privately rather than sending them. The runner refuses non-test names, remote
database hosts, and test databases with existing members, questions, answer
logs, or rate-limit rows.
For a private database on another TCP port, use `TEST_DB_HOST=127.0.0.1` and
set `TEST_DB_PORT` to that port. The suite includes public-page smoke checks
and verifies local startup without `APP_URL`, including email links under a
forged request `Host` header.

The database-independent checks are `php tests/test_db_guard_test.php` and
`php tests/question_authoring_test.php`. The end-to-end suite is separate because
it requires the isolated local test database described above.

## Production PHP settings

The checked-in `php/php.ini` records the required account settings, but cPanel controls
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

Sources: [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-5)
and [session upload-progress advisory](https://www.php.net/archive/2026.php#2026-10-23-1).

## Database schema

`schema.sql` is the only database setup. It creates the tables `members`,
`questions`, `answerlog`, `hint_uses`, `request_limits`, and `event_state`, and
starts with the event closed. There are no upgrade scripts: when you change the
schema, update `schema.sql` and recreate your local and test databases from it.

## Question authoring tool (work in progress)

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
references must use an ID in `php/includes/media-catalog.php`; HTML, external or
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
