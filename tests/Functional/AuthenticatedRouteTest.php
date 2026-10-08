<?php

namespace App\Tests\Functional;

use App\Tests\Support\Context;
use App\Tests\Support\Probe;
use App\Tests\Support\Routes;
use PHPUnit\Framework\TestCase;

/**
 * The 22 routes that need a token, this time with one.
 *
 * This is the sweep that actually executes the actions. With a valid
 * Authorization header the request gets past App\Service\Signature and
 * App\Service\Token, so the controller body runs: validators, repositories,
 * the custom round()/rand() DQL functions, the serializer and the counters.
 *
 * Where a probe declares `expect`, that status is asserted as well. The rest only
 * have to answer with the application's envelope and something below 500 - which
 * for a route whose whole purpose is to reject bad input (a wrong verification
 * code, a nonexistent id) is the honest bar.
 */
final class AuthenticatedRouteTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (Context::configured()) {
            Context::bootstrap();
        }
    }

    /**
     * @dataProvider authenticatedProbeProvider
     */
    public function testAuthenticatedRouteFullyExecutes(array $probe): void
    {
        if (!Context::configured()) {
            self::markTestSkipped(Context::notConfiguredReason());
        }

        $client   = Context::clientFor(isset($probe['as']) ? $probe['as'] : null);
        $response = Probe::send($client, $probe);

        self::assertNull(
            $response->transportError(),
            'no usable response: ' . $response->describe()
        );
        self::assertTrue(
            $response->isEnvelope(),
            'the response is not the application\'s JSON envelope: ' . $response->describe()
        );
        self::assertLessThan(
            500,
            $response->status(),
            'the route answered with a server error: ' . $response->describe()
        );

        if (isset($probe['expect'])) {
            self::assertContains(
                $response->status(),
                $probe['expect'],
                'unexpected status' . (isset($probe['note']) ? ' (' . $probe['note'] . ')' : '') . ': ' . $response->describe()
            );
        }
    }

    /**
     * The mirror image: these routes must refuse a request that carries no token.
     *
     * Without this, a controller that forgot to call getCurrentUser() would look
     * identical to a protected one in the sweeps above, because both would answer
     * 200 once a token is present.
     */
    public function testProtectedRoutesRefuseAMissingToken(): void
    {
        if (!Context::configured()) {
            self::markTestSkipped(Context::notConfiguredReason());
        }

        $anonymous = Context::anonymous();

        $cases = [
            'GET /user/self'             => $anonymous->get('/user/self'),
            'PATCH /user/'               => $anonymous->patch('/user/', ['sex' => 'MAN']),
            'GET /article/list/self'     => $anonymous->get('/article/list/self'),
            'GET /user/avatar/history'   => $anonymous->get('/user/avatar/history'),
            'GET /user/password/code'    => $anonymous->get('/user/password/code'),
            'POST /article/'             => $anonymous->post('/article/', [
                'title' => 'nope', 'content' => 'nope', 'description' => 'nope', 'tag' => [],
            ]),
        ];

        foreach ($cases as $what => $response) {
            self::assertSame(
                401,
                $response->status(),
                $what . ' should require a token: ' . $response->describe()
            );
            self::assertSame(
                10001,
                $response->errorCode(),
                $what . ' should answer with the token error code: ' . $response->describe()
            );
        }
    }

    /**
     * An unsigned request must be refused before any of that, which is also the
     * check that ApiClient is signing correctly rather than being let through.
     */
    public function testRequestsWithoutASignatureAreRefused(): void
    {
        if (!Context::configured()) {
            self::markTestSkipped(Context::notConfiguredReason());
        }

        // Deliberately not via ApiClient: it always signs. A raw call with no
        // headers at all is what this asserts against.
        $ch = curl_init(Context::anonymous()->baseUrl() . '/tag/hot');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        self::assertSame(401, $status, 'an unsigned request must be refused: ' . $body);
        self::assertStringContainsString('signature', $body);
    }

    public static function authenticatedProbeProvider(): array
    {
        $datasets = [];
        foreach (Routes::probes() as $probe) {
            if (!isset($probe['as'])) {
                continue;
            }

            $datasets[$probe['id'] . ' [' . $probe['as'] . ']'] = [$probe];
        }

        return $datasets;
    }
}
