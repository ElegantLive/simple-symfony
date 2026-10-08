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
    $tail = array_values(array_filter(
        array_map('trim', array_slice(file($appLog, FILE_IGNORE_NEW_LINES), -6)),
        function ($line) {
            return $line !== '';
        }
    ));

    // Verbatim, at whatever level, rather than matched against a pattern: the point
    // is to rule the log in or out, and a regex that misses the format silently
    // reports "the application logged nothing".
    foreach (array_slice($tail, -3) as $line) {
        $cut = strpos($line, ' {"');
        if ($cut !== false) {
            $line = substr($line, 0, $cut);
        }
        $lines[] = 'test.log: ' . mb_substr($line, 0, 350);
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
$byPath    = [];
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
            $reason = 'not the application envelope';
        }

        if ($reason !== null) {
            $where      = sprintf('%s %s', $row['method'] ?? '?', $row['path'] ?? '?');
            $problems[] = sprintf('%s (%s) -> %s', $where, $row['label'] ?? '?', $reason);

            if (!isset($byPath[$where])) {
                $byPath[$where] = ['count' => 0, 'reason' => $reason];
            }
            $byPath[$where]['count']++;

            // The body is the part that matters when the response is not the
            // application's JSON: it is PHP's own error output or a framework error
            // page, and those name the cause. One per distinct body, so three
            // identical failures do not use up the annotation budget.
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
    $text = $body;

    if (stripos($text, '<html') !== false || stripos($text, '<!DOCTYPE') !== false) {
        // Symfony's error pages open with a large <style> block, so a raw prefix
        // is nothing but CSS. Drop the styles and scripts, then the tags.
        $text = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', ' ', $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = 'html page: ' . $text;
    }

    $lines[] = $where . ' responded with: ' . mb_substr(trim(preg_replace('/\s+/', ' ', (string) $text)), 0, 400);
}

// Which routes, grouped: the full list does not fit in an annotation, but the set
// of distinct paths does, and that is what says whether the failure is everywhere
// or confined to one controller.
$groups = [];
foreach ($byPath as $where => $info) {
    $groups[] = $where . ' x' . $info['count'];
}

foreach (array_chunk($groups, 6) as $chunk) {
    $lines[] = 'failed: ' . implode(' | ', $chunk);
}

// ------------------------------------------------------------ what is in var/log

// If the application logged nothing, saying so out loud is worth more than an
// empty annotation: it rules the log out as a source.
$logDir = dirname(__DIR__, 2) . '/var/log';
$inventory = [];
foreach (glob($logDir . '/*') ?: [] as $file) {
    $inventory[] = basename($file) . '=' . filesize($file) . 'B';
}
$lines[] = 'var/log: ' . ($inventory ? implode(' ', $inventory) : '(empty or missing)');

// What the step's own environment actually held. The application is started from
// this shell, so a value missing here was missing there too - and .env.test is the
// only other place it could have come from.
$lines[] = sprintf(
    'step env: APP_ENV=%s APP_DEBUG=%s DATABASE_URL=%s MAILER_DSN=%s MESSENGER_TRANSPORT_DSN=%s',
    var_export(getenv('APP_ENV'), true),
    var_export(getenv('APP_DEBUG'), true),
    getenv('DATABASE_URL') === false ? 'MISSING' : 'set',
    getenv('MAILER_DSN') === false ? 'MISSING' : 'set',
    getenv('MESSENGER_TRANSPORT_DSN') === false ? 'MISSING' : 'set'
);

// ------------------------------------------------------------- the test summary

if ($phpUnitOutput !== null && is_file($phpUnitOutput)) {
    $text = (string) file_get_contents($phpUnitOutput);

    if (preg_match('/^(Tests: .*)$/m', $text, $match) === 1) {
        $lines[] = $match[1];
    } elseif (preg_match('/^(OK \(.*\))$/m', $text, $match) === 1) {
        $lines[] = $match[1];
    }

    // The heading alone says which test; the lines under it say why, and the why is
    // the whole point of the annotation. PHPUnit 9 prints "N) test name" followed by
    // the exception or assertion message.
    $linesOfOutput = preg_split('/\r?\n/', $text);
    $captured      = 0;

    foreach ($linesOfOutput as $index => $line) {
        if (preg_match('/^\d+\) (.+)$/', $line, $match) !== 1) {
            continue;
        }

        $detail = '';
        for ($i = $index + 1; $i < min($index + 4, count($linesOfOutput)); $i++) {
            $candidate = trim($linesOfOutput[$i]);
            if ($candidate !== '' && strpos($candidate, 'Failed asserting') !== false) {
                $detail = $candidate;
                break;
            }
            if ($detail === '' && $candidate !== '' && strpos($candidate, '/') !== 0) {
                $detail = $candidate;
            }
        }

        $lines[] = 'failed: ' . trim($match[1]) . ($detail === '' ? '' : ' :: ' . mb_substr($detail, 0, 300));

        if (++$captured >= 3) {
            break;
        }
    }
}

if (!$lines) {
    $lines[] = 'the functional run failed and produced neither a failing request nor a test summary';
}

// -------------------------------------------------------------------- emit them

foreach (array_slice($lines, 0, 10) as $line) {
    // GitHub wants one line per command, with these three characters escaped.
    echo '::error::' . str_replace(
        ["\r", "\n", '%'],
        ['%0D', '%0A', '%25'],
        $line
    ) . "\n";
}

