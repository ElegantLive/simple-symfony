<?php

/**
 * Checks that App\Tests\Support\Routes::ROUTES describes the same application the
 * router does.
 *
 * The sweeps can only cover the routes the registry knows about, so a route that
 * is added to a controller and never registered would go untested while every test
 * still passed. CI runs this as its own step (see .github/workflows/ci.yml) and
 * fails the job on any difference, in either direction.
 *
 * Run it the same way the application is run, so the same route files are loaded:
 *
 *   APP_ENV=test php tests/Support/check-routes.php
 *
 * Exit codes: 0 in step, 1 on a mismatch, 2 when the router could not be read at
 * all (which is a different problem and should not look like a route diff).
 */

use App\Tests\Support\Routes;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);

$command = escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($root . '/bin/console')
    . ' debug:router --format=json --no-ansi 2>&1';

$output = [];
$status = 0;
exec($command, $output, $status);

$raw = implode("\n", $output);

if ($status !== 0) {
    fwrite(STDERR, "could not read the router:\n" . $raw . "\n");
    exit(2);
}

$routes = json_decode($raw, true);

if (!is_array($routes)) {
    fwrite(STDERR, "debug:router did not return JSON:\n" . $raw . "\n");
    exit(2);
}

$problems = [];

foreach (Routes::ROUTES as $name => $expected) {
    list($method, $path) = $expected;

    if (!isset($routes[$name])) {
        $problems[] = sprintf('the registry knows "%s" (%s %s) but the router does not', $name, $method, $path);
        continue;
    }

    $actualPath = isset($routes[$name]['path']) ? $routes[$name]['path'] : null;
    if ($actualPath !== $path) {
        $problems[] = sprintf('"%s" is %s in the router but %s in the registry', $name, $actualPath, $path);
    }

    $methods = isset($routes[$name]['method']) ? (array) $routes[$name]['method'] : [];
    if (!in_array($method, $methods, true)) {
        $problems[] = sprintf('"%s" accepts %s in the router but the registry says %s', $name, implode(',', $methods), $method);
    }
}

foreach (array_keys($routes) as $name) {
    if (!isset(Routes::ROUTES[$name])) {
        $problems[] = sprintf(
            'the router exposes "%s" (%s) but the registry has no entry for it - add a probe so the sweeps cover it',
            $name,
            isset($routes[$name]['path']) ? $routes[$name]['path'] : '?'
        );
    }
}

if ($problems) {
    fwrite(STDERR, "the route registry is out of step with the router:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, '  - ' . $problem . "\n");
    }
    exit(1);
}

printf(
    "the route registry matches the router: %d routes, all of them probed\n",
    count(Routes::ROUTES)
);
