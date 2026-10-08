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

// ------------------------------------------------------------ failing requests

$problems = [];
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
            $problems[] = sprintf(
                '%s %s -> %s (%s)',
                $row['method'] ?? '?',
                $row['path'] ?? '?',
                $reason,
                $row['label'] ?? '?'
            );
        }
    }
}

if ($problems) {
    $lines[] = sprintf('%d request(s) failed:', count($problems));
    foreach (array_slice($problems, 0, 6) as $problem) {
        $lines[] = '  ' . $problem;
    }
}

// ------------------------------------------------- where the application blew up

// var/log/test.log records one entry per unhandled exception, with the file and
// line it came from. That is the thing worth knowing about a 500, and it is not
// in the response body.
$appLog = dirname(__DIR__, 2) . '/var/log/test.log';
if (is_file($appLog)) {
    $log     = (string) file_get_contents($appLog);
    $sites   = [];
    $matches = [];

    if (preg_match_all('/"file":"([^"]+)","line":(\d+)/', $log, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            // Vendor frames are noise here; a frame inside src/ is the answer.
            if (strpos($match[1], '/src/') === false) {
                continue;
            }
            $sites[] = basename($match[1]) . ':' . $match[2];
        }
    }

    $sites = array_values(array_unique($sites));
    if ($sites) {
        $lines[] = 'exception sites in src/ (from var/log/test.log): ' . implode(', ', $sites);
    }
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
        foreach (array_slice($matches[1], 0, 5) as $heading) {
            $lines[] = 'failed: ' . trim($heading);
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
