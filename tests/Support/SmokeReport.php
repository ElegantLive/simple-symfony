<?php

namespace App\Tests\Support;

/**
 * Appends one JSON object per probe to a log file, so the run can be turned into
 * a coverage report afterwards.
 *
 * Written line by line and flushed immediately on purpose: if the suite dies
 * half way through - which is a real possibility here, one of the routes can send
 * the single-threaded PHP built-in server into an infinite loop - the probes that
 * did run are still on disk.
 *
 * The path comes from SMOKE_REPORT and defaults to var/smoke-report.jsonl.
 */
final class SmokeReport
{
    /** @var string|null */
    private static $path;

    /** @var resource|null */
    private static $handle;

    /** @var int */
    private static $written = 0;

    /** @var bool */
    private static $started = false;

    public static function path(): string
    {
        if (self::$path === null) {
            $configured = getenv('SMOKE_REPORT');
            self::$path = $configured !== false && $configured !== ''
                ? $configured
                : dirname(__DIR__, 2) . '/var/smoke-report.jsonl';
        }

        return self::$path;
    }

    /**
     * Start a clean log. Called once per run by the first test class.
     */
    public static function reset(): void
    {
        if (self::$handle !== null) {
            fclose(self::$handle);
            self::$handle = null;
        }

        $directory = dirname(self::path());
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(self::path(), '');
        self::$written = 0;
        self::$started = true;
    }

    /**
     * @param array $row
     */
    public static function record(array $row): void
    {
        // Truncate on the first write of the process whatever the test order is,
        // so a run that starts with a different test class still produces a log
        // containing only this run's probes.
        if (!self::$started) {
            self::reset();
        }

        if (self::$handle === null) {
            self::$handle = fopen(self::path(), 'a');
        }

        if (self::$handle === false) {
            self::$handle = null;
            return;
        }

        fwrite(self::$handle, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        fflush(self::$handle);
        self::$written++;
    }

    public static function written(): int
    {
        return self::$written;
    }
}
