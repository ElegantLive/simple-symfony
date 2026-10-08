<?php
/**
 * Wait until the database referenced by DATABASE_URL accepts connections.
 *
 * Installed at /usr/local/bin/wait-for-db.php inside the image (see the
 * Dockerfile) so it never depends on the app source being bind-mounted.
 *
 * It reuses the application's own .env loading (Symfony Dotenv) and parses the
 * DSN the same way the app does, instead of re-implementing that in shell.
 *
 * Usage:
 *   wait-for-db.php                wait until the DB answers, then exit 0
 *   wait-for-db.php --print-host   print the DSN's host and exit (for the shell
 *                                  fallback that uses mysqladmin)
 *
 * Environment:
 *   DB_WAIT_TIMEOUT  total seconds to keep retrying (default 120)
 *   DB_WAIT_INTERVAL seconds between attempts (default 2)
 */

$appDir = getenv('APP_DIR') ?: '/var/www/html';

require $appDir . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Dotenv\Dotenv;

// Real environment variables win over .env, which is what docker-compose relies on.
(new Dotenv(false))->loadEnv($appDir . '/.env');

$dsn = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? (getenv('DATABASE_URL') ?: null);
if (!$dsn) {
    fwrite(STDERR, "[wait-for-db] DATABASE_URL is not set, skipping wait\n");
    exit(0);
}

// DATABASE_URL is a URL (mysql://user:pass@host:port/db?serverVersion=...), and
// PDO cannot consume that form: handing it straight to `new PDO()` makes the
// driver treat the whole string as a socket path and fail with
// "SQLSTATE[HY000] [2002] No such file or directory". The split-off query string
// is only needed by the hostname regex below.
$url = $dsn;
$query = '';
if (false !== $pos = strpos($dsn, '?')) {
    $query = substr($dsn, $pos);
    $dsn = substr($dsn, 0, $pos);
}

// Only used by the shell fallback in the entrypoint; the hostname is all it needs.
if (in_array('--print-host', $argv ?? [], true)) {
    $authority = preg_replace('#^[a-zA-Z0-9+.\-]*://#', '', $dsn);   // strip scheme
    $authority = preg_replace('#^[^@/]*@#', '', $authority);         // strip credentials
    $host = preg_split('#[:/]#', $authority)[0] ?? '';

    echo trim($host, '[]');
    exit(0);
}

$timeout = (int) (getenv('DB_WAIT_TIMEOUT') ?: 120);
$interval = (int) (getenv('DB_WAIT_INTERVAL') ?: 2);
$deadline = microtime(true) + $timeout;

$attempt = 0;
while (true) {
    ++$attempt;

    try {
        // DBAL parses the URL exactly the way the application does (it is what
        // backs DATABASE_URL), then connects through the real driver.
        DriverManager::getConnection(['url' => $url])->connect();

        printf("[wait-for-db] connected after %d attempt(s)%s\n", $attempt, $query ? " ($query)" : '');
        exit(0);
    } catch (Throwable $e) {
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, sprintf(
                "[wait-for-db] gave up after %ds (%d attempts): %s\n",
                $timeout,
                $attempt,
                $e->getMessage()
            ));
            exit(1);
        }

        printf("[wait-for-db] attempt %d failed, retrying in %ds ...\n", $attempt, $interval);
        sleep($interval);
    }
}
