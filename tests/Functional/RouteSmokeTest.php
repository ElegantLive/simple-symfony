<?php

namespace App\Tests\Functional;

use App\Tests\Support\Context;
use App\Tests\Support\Probe;
use App\Tests\Support\Routes;
use PHPUnit\Framework\TestCase;

/**
 * Every route in the router, requested once with no token.
 *
 * This is the floor, not the ceiling. It proves a route is wired, the container
 * compiles, its controller can be instantiated and its arguments resolve - and it
 * is exactly what the brief asked for: answer something, just not a 500.
 *
 * It is worth being clear about how little that means for the 22 routes that need
 * a token: without one they stop at App\Service\Token and never enter the action,
 * so a green run here says nothing about their bodies. AuthenticatedRouteTest is
 * where those actually execute; this class is the smoke test.
 *
 * Two assertions instead of one, because "status < 500" alone is not enough: a
 * fatal error escaping the application's error handling can be served with HTTP
 * 200 and PHP's error text as the body. That was observed for real while building
 * this suite (a mailer misconfiguration inside the exception subscriber's own
 * failure path), so the body has to be the application's JSON envelope too.
 */
final class RouteSmokeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (Context::configured()) {
            Context::bootstrap();
        }
    }

    /**
     * @dataProvider probeProvider
     */
    public function testRouteAnswersWithoutServerError(array $probe): void
    {
        if (!Context::configured()) {
            self::markTestSkipped(Context::notConfiguredReason());
        }

        $response = Probe::send(Context::anonymous(), $probe);

        self::assertNull(
            $response->transportError(),
            'no usable response: ' . $response->describe()
        );
        self::assertTrue(
            $response->isEnvelope(),
            'the response is not the application\'s JSON envelope, so it came from PHP or the web server '
            . 'rather than from a controller: ' . $response->describe()
        );
        self::assertLessThan(
            500,
            $response->status(),
            'the route answered with a server error: ' . $response->describe()
        );
    }

    /**
     * The registry and the router have to describe the same application. This
     * checks the registry half without booting anything; check-routes.php checks
     * the other half against bin/console debug:router, and CI runs both.
     */
    public function testEveryRegisteredRouteHasAtLeastOneProbe(): void
    {
        $covered = [];
        foreach (Routes::probes() as $probe) {
            $covered[$probe['route']] = true;
        }

        $missing = array_values(array_diff(array_keys(Routes::ROUTES), array_keys($covered)));

        self::assertSame(
            [],
            $missing,
            'these routes appear in Routes::ROUTES but no probe requests them, so the sweep would miss them'
        );
    }

    /**
     * @dataProvider probeProvider
     */
    public function testEveryProbeNamesARealRoute(array $probe): void
    {
        self::assertArrayHasKey(
            $probe['route'],
            Routes::ROUTES,
            'probe "' . $probe['id'] . '" has no entry in Routes::ROUTES, so it would not count towards coverage'
        );
    }

    public static function probeProvider(): array
    {
        $datasets = [];
        foreach (Routes::probes() as $probe) {
            $datasets[$probe['id']] = [$probe];
        }

        return $datasets;
    }
}
