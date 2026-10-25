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
incoming request host. Before deployment, review and apply the SQL scripts in
`migrations/` against a verified backup; the application does not run them.

Sources: [PHP security advisory](https://www.php.net/archive/2026.php#2026-10-21-5)
and [session upload-progress advisory](https://www.php.net/archive/2026.php#2026-10-23-1).

Question media is served by `media.php` from an explicit file map and is
available only at or below the player's current level. Keep the web-server
access rules in `accessible/.htaccess` and `questions/.htaccess` enabled; if a
front-end server ignores Apache rules, deny direct access to both directories
there as well.
