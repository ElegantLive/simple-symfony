<?php

/**
 * Turns a functional-test run into the coverage report.
 *
 * Two questions, answered from evidence rather than by assertion:
 *
 *   Which routes answered, and how far each request got?
 *     Every request the suite makes is appended to var/smoke-report.jsonl by
 *     App\Tests\Support\ApiClient, with its route name, status and application
 *     error code. A request that came back with the signature (10007) or token
 *     (10001) error never reached the action; everything else did.
 *
 *   Which source files does that reach?
 *     bin/console debug:router maps each route to its controller, and the
 *     controller to a file. Files no route points at are listed too, so a
 *     controller nobody wired up shows up as unreached instead of being invisible.
 *
 * What this deliberately does NOT claim is line coverage. The functional requests
 * are served by a separate PHP process, so the PHPUnit process's coverage driver
 * cannot see them; measuring that needs instrumentation inside the web server. The
 * report says so rather than printing a number it cannot support.
 *
 * Usage (the application must be runnable, so APP_ENV has to be set):
 *   APP_ENV=test php tests/Support/route-coverage.php
 */

use App\Tests\Support\Routes;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root       = dirname(__DIR__, 2);
$reportPath = getenv('SMOKE_REPORT') ?: $root . '/var/smoke-report.jsonl';

// ---------------------------------------------------------------- the requests

$probes = [];
if (is_file($reportPath)) {
    foreach (file($reportPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $probes[] = $row;
        }
    }
}

// ---------------------------------------------------------------- the router

$command = escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($root . '/bin/console')
    . ' debug:router --format=json --no-ansi 2>&1';

$output = [];
$status = 0;
exec($command, $output, $status);

$router = $status === 0 ? json_decode(implode("\n", $output), true) : null;
if (!is_array($router)) {
    fwrite(STDERR, "could not read the router; APP_ENV is probably not set\n" . implode("\n", $output) . "\n");
    exit(2);
}

// A request that stopped at the signature or token guard never entered the action.
const GUARD_ERROR_CODES = [10001, 10007];

$signatureErrors = [10007];

/** @var array $byRoute route name => ['anon' => int, 'auth' => int, 'executed' => bool, 'statuses' => []] */
$byRoute = [];
foreach (array_keys(Routes::ROUTES) as $name) {
    $byRoute[$name] = ['anon' => 0, 'auth' => 0, 'executed' => 0, 'statuses' => []];
}

foreach ($probes as $probe) {
    $route = $probe['route'] ?? null;
    if ($route === null || !isset($byRoute[$route])) {
        continue;
    }

    $code     = $probe['errorCode'] ?? null;
    $guarded  = in_array($code, GUARD_ERROR_CODES, true);
    $envelope = !empty($probe['envelope']);

    if (!empty($probe['auth'])) {
        $byRoute[$route]['auth']++;
    } else {
        $byRoute[$route]['anon']++;
    }

    // The action ran when the application produced its own envelope, the request
    // did not stop at a guard, and it did not end in a server error. Note this is
    // deliberately independent of whether a token was sent: 13 of the 36 routes are
    // public, and for those an anonymous request is the one that reaches the action.
    if ($envelope && !$guarded && (int) $probe['status'] < 500) {
        $byRoute[$route]['executed']++;
    }

    $byRoute[$route]['statuses'][(string) $probe['status']] =
        ($byRoute[$route]['statuses'][(string) $probe['status']] ?? 0) + 1;
}

// ---------------------------------------------------------------- the report

$controllers = [];

echo "# Route coverage\n\n";
printf(
    "Requests recorded: %d. Routes in the router: %d. Routes whose action ran: %d.\n\n",
    count($probes),
    count(Routes::ROUTES),
    count(array_filter($byRoute, function (array $r) {
        return $r['executed'] > 0;
    }))
);

echo "`executed` means the application answered with its own JSON envelope, was not\n";
echo "stopped by the signature or token guard, and did not answer 5xx.\n\n";

echo "| route | method | path | controller | anon | with token | executed | statuses seen |\n";
echo "|---|---|---|---|---|---|---|---|\n";

foreach (Routes::ROUTES as $name => $definition) {
    list($method, $path) = $definition;

    $controller = isset($router[$name]['defaults']['_controller'])
        ? (string) $router[$name]['defaults']['_controller']
        : '?';

    if (strpos($controller, '::') !== false) {
        list($class, $action) = explode('::', $controller);
        if (class_exists($class)) {
            $file = (new ReflectionClass($class))->getFileName();
            if ($file) {
                $controllers[$file] = isset($controllers[$file]) ? $controllers[$file] : 0;
                if ($byRoute[$name]['executed'] > 0) {
                    $controllers[$file]++;
                }
            }
        }
        $controller = $class . '::' . $action;
    }

    $statuses = [];
    foreach ($byRoute[$name]['statuses'] as $code => $count) {
        $statuses[] = $count > 1 ? $code . '×' . $count : $code;
    }

    printf(
        "| %s | %s | %s | %s | %d | %d | %s | %s |\n",
        $name,
        $method,
        $path,
        $controller,
        $byRoute[$name]['anon'],
        $byRoute[$name]['auth'],
        $byRoute[$name]['executed'] > 0 ? 'yes' : '**no**',
        $statuses ? implode(' ', $statuses) : '-'
    );
}

// ------------------------------------------------------- which files that reaches

echo "\n# Files the functional suite reaches\n\n";

$neverRequested = [];
$controlled = [];
foreach ($router as $name => $definition) {
    $controller = isset($definition['defaults']['_controller']) ? (string) $definition['defaults']['_controller'] : '';
    if (strpos($controller, '::') === false) {
        continue;
    }
    $class = explode('::', $controller)[0];
    if (!class_exists($class)) {
        continue;
    }
    $file = (new ReflectionClass($class))->getFileName();
    if ($file) {
        $controlled[$file] = true;
    }
}

$controllerFiles = glob($root . '/src/Controller/*.php');
sort($controllerFiles);

echo "| controller file | routed | action executed |\n|---|---|---|\n";
foreach ($controllerFiles as $file) {
    $routed = isset($controlled[$file]);
    $ran    = isset($controllers[$file]) && $controllers[$file] > 0;

    printf(
        "| src/Controller/%s | %s | %s |\n",
        basename($file),
        $routed ? 'yes' : '**no route**',
        $ran ? 'yes' : '**no**'
    );

    if (!$routed) {
        $neverRequested[] = $file;
    }
}

// ------------------------------------------------------------------- src/ files

echo "\n# Every file under src/, and how it is covered\n\n";
echo "`unit test?` is a name match against tests/ (App\\Tests\\...), which is a\n";
echo "heuristic: it says a test file mentions the class, not that every line is\n";
echo "exercised. `has a route` is only ever yes for a controller - entities,\n";
echo "repositories and services are reached from one, which this table cannot prove.\n\n";
echo "| file | kind | has a route | unit test file mentions it |\n|---|---|---|---|\n";

$unitSources = '';
foreach (glob($root . '/tests/{Entity,Functional,MessageHandler,Rule,Service,Validator,Support}/*.php', GLOB_BRACE) as $file) {
    if (strpos($file, '/Functional/') !== false || strpos($file, '/Support/') !== false) {
        continue;
    }
    $unitSources .= (string) file_get_contents($file);
}

$srcFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $srcFiles[] = $file->getPathname();
    }
}
sort($srcFiles);

foreach ($srcFiles as $file) {
    $relative = 'src/' . ltrim(str_replace($root . '/src', '', $file), '/');
    $parts    = explode('/', $relative);
    $kind     = count($parts) > 2 ? $parts[1] : 'root';
    $class    = pathinfo($file, PATHINFO_FILENAME);

    printf(
        "| %s | %s | %s | %s |\n",
        $relative,
        $kind,
        isset($controlled[$file]) ? 'yes' : 'no',
        strpos($unitSources, $class) !== false ? 'yes' : 'no'
    );
}

echo "\n# What this does not measure\n\n";
echo "- Line or branch coverage of the functional run. Those requests are served by a\n";
echo "  separate PHP process, so the coverage driver in the PHPUnit process cannot see\n";
echo "  them; that needs instrumentation inside the web server, which is not set up.\n";
echo "- Whether an entity, repository or service was actually exercised. They are\n";
echo "  reached indirectly, and only a coverage driver can confirm that.\n";
