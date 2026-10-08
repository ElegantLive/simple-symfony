<?php

/**
 * Turns a failing functional run into GitHub annotations.
 *
 * The job log and the uploaded artifacts both need admin rights to read, even on
 * a public repository. Annotations do not: they come back from
 * /repos/{owner}/{repo}/check-runs/{id}/annotations with no credentials, so
 * putting the failure summary and the offending requests here is what makes a red
 * run diagnosable from outside.
 *
 * Usage (from the failing step):
 *   php tests/Support/annotate-failures.php "$RUNNER_TEMP/phpunit-functional.txt"
 *
 * GitHub accepts a limited number of error annotations per step, so the output is
 * capped and the most specific evidence - the requests that actually failed - is
 * emitted first.
 */

$phpUnitOutput = $argv[1] ?? null;
$reportPath    = getenv('SMOKE_REPORT') ?: dirname(__DIR__, 2) . '/var/smoke-report.jsonl';

$lines = [];

// ------------------------------------------------------- what Symfony logged

// var/log/test.log is where the message actually is. When a response comes back as
// Symfony's own error page rather than the application's JSON, nothing in the
// response says why: the non-debug error page deliberately hides the exception.
// The framework's ErrorListener logs it at critical, and the monolog test handler
// is fingers_crossed on error, so it is in this file.
$appLog = dirname(__DIR__, 2) . '/var/log/test.log';
if (is_file($appLog)) {
    $seen = [];

    foreach (file($appLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (stripos($line, 'CRITICAL') === false && stripos($line, 'app.ERROR') === false) {
            continue;
        }

        // "[2026-01-01 00:00:00] request.CRITICAL: Uncaught PHP Exception ..." - the
        // message starts after the level marker.
        if (preg_match('/\]\s+[a-z_.]+\.(?:CRITICAL|ERROR):\s*(.+)$/i', $line, $match) !== 1) {
            continue;
        }

        $message = trim(preg_replace('/\s+/', ' ', $match[1]));

        // Drop the context payload monolog appends; the message is the interesting
        // part and the context only pushes it past the annotation limit.
        $cut = strpos($message, ' {"');
        if ($cut !== false) {
            $message = substr($message, 0, $cut);
        }

        if ($message === '' || isset($seen[$message])) {
            continue;
        }

        $seen[$message] = true;
    }

    foreach (array_slice(array_keys($seen), -3) as $message) {
        $lines[] = 'logged: ' . mb_substr($message, 0, 400);
    }
}

// ------------------------------------------------------- what PHP itself said

// The built-in server's stderr. A failure the framework never got to handle - a
// parse error, a fatal in the front controller, a warning turned into an
// exception - is only ever reported here, and this is the first thing worth
// knowing about.
$serverLog = getenv('SMOKE_SERVER_LOG');
if ($serverLog !== false && $serverLog !== '' && is_file($serverLog)) {
    $tail = array_values(array_filter(
        array_map('trim', array_slice(file($serverLog, FILE_IGNORE_NEW_LINES), -40)),
        function ($line) {
            // The built-in server logs one line per request; the interesting ones
            // are the errors.
            return $line !== '' && stripos($line, 'Accepted') === false && stripos($line, 'Closing') === false;
        }
    ));

    foreach (array_slice($tail, -3) as $line) {
        $lines[] = 'server: ' . mb_substr($line, 0, 300);
    }
}

// ------------------------------------------------------------ failing requests

$problems  = [];
$failures  = [];
if (is_file($reportPath)) {
    foreach (file($reportPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if (!is_array($row)) {
            continue;
        }

        $reason = null;
        if (!empty($row['curlError'])) {
            $reason = 'no response: ' . $row['curlError'];
        } elseif ((int) $row['status'] >= 500) {
            $reason = 'HTTP ' . $row['status'] . ' ' . ($row['message'] ?? '');
        } elseif (empty($row['envelope'])) {
            $reason = 'not the application envelope: ' . mb_substr((string) ($row['body'] ?? ''), 0, 120);
        }

        if ($reason !== null) {
            $where      = sprintf('%s %s (%s)', $row['method'] ?? '?', $row['path'] ?? '?', $row['label'] ?? '?');
            $problems[] = $where . ' -> ' . $reason;

            // The body is the part that matters when the response is not the
            // application's JSON: it is PHP's own error output, and it names the
            // file and line. One per distinct body, so three identical failures do
            // not use up the annotation budget.
            $body = trim((string) ($row['body'] ?? ''));
            if ($body !== '' && !isset($failures[$body])) {
                $failures[$body] = $where;
            }
        }
    }
}

// The bodies go first: they are the only place a PHP-level failure explains
// itself, and GitHub caps how many annotations a step may emit.
foreach (array_slice($failures, 0, 3, true) as $body => $where) {
    $lines[] = $where . ' responded with: ' . mb_substr(preg_replace('/\s+/', ' ', $body), 0, 400);
}

// ------------------------------------------------------------- the test summary

if ($phpUnitOutput !== null && is_file($phpUnitOutput)) {
    $text = (string) file_get_contents($phpUnitOutput);

    if (preg_match('/^(Tests: .*)$/m', $text, $match) === 1) {
        $lines[] = $match[1];
    } elseif (preg_match('/^(OK \(.*\))$/m', $text, $match) === 1) {
        $lines[] = $match[1];
    }

    if (preg_match_all('/^\d+\) (.+)$/m', $text, $matches) === 1 || !empty($matches[1])) {
        foreach (array_slice($matches[1], 0, 3) as $heading) {
            $lines[] = 'failed: ' . trim($heading);
        }
    }
}

if ($problems) {
    $lines[] = sprintf('%d request(s) failed, first few:', count($problems));
    foreach (array_slice($problems, 0, 4) as $problem) {
        $lines[] = '  ' . $problem;
    }
}

if (!$lines) {
    $lines[] = 'the functional run failed and produced neither a failing request nor a test summary';
}

// -------------------------------------------------------------------- emit them

foreach (array_slice($lines, 0, 9) as $line) {
    // GitHub wants one line per command, with these three characters escaped.
    echo '::error::' . str_replace(
        ["\r", "\n", '%'],
        ['%0D', '%0A', '%25'],
        $line
    ) . "\n";
}

