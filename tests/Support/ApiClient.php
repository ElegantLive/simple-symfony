<?php

namespace App\Tests\Support;

/**
 * An HTTP client for the smoke tests.
 *
 * Two things make this more than a curl wrapper.
 *
 * 1. It signs requests instead of turning the signature requirement off.
 *
 *    Every non-dev request passes through App\EventListener\Request, which calls
 *    App\Service\Signature::checkSign() and then
 *    App\Service\ParameterCheck::checkParams(). Neither is skipped in the test
 *    environment - the listener only returns early when APP_ENV is exactly 'dev'.
 *    A client that does not sign therefore gets as far as "signature miss" (HTTP
 *    401) and the controller never runs, which is what makes an unsigned sweep
 *    almost worthless: 22 of the 36 routes need a token as well, so an unsigned,
 *    untokened sweep would report "not a 500" for them without executing a single
 *    line of the action.
 *
 *    The algorithm is reproduced here rather than by calling the application's own
 *    services, because those services read the request out of PHP's superglobals
 *    (App\Service\Request::createFromGlobals()) and would see this process's CLI
 *    environment, not the request being made. Reproducing it has one advantage:
 *    the same client works against dev, test and prod, because a correct
 *    signature is accepted everywhere and simply not looked at where the guard is
 *    off. Drift between this and the application shows up immediately as a 401
 *    "signature invalid", so it cannot pass silently.
 *
 *     - signature  = base64(openssl_public_encrypt(http_build_query(ksort([
 *                      time, once, url=path without query, platform, char ]))))
 *                    The server decrypts it with the *private* sign key, which is
 *                    why the client only needs the public one.
 *     - parameter  = md5(str_replace('+', '%20', http_build_query(ksort(
 *                      body + [time, once]))) . char)
 *                    ParameterCheck rebuilds exactly this from the request body
 *                    plus the time/once headers. Query-string parameters are NOT
 *                    part of it - App\Service\Request::getData() only reads a JSON
 *                    body or POST fields - so they are unsigned, which is how the
 *                    application is written, not a shortcut taken here.
 *     - char       travels inside the encrypted signature and is the MAC secret.
 *                    Any non-empty value works; ParameterCheck rejects an empty one.
 *     - once       must be unique for 60 seconds, because Signature::checkOnce
 *                    caches it under "request=<once>" and rejects a repeat.
 *
 * 2. It never throws on an HTTP status.
 *
 *    A 4xx is the expected answer for most of these probes, so the status is data,
 *    not an error. Only a transport failure (including the timeout) is recorded,
 *    and the tests treat that as a failure of its own.
 */
final class ApiClient
{
    /** A long enough wait that a slow first request is fine, short enough that an
     *  endpoint which never returns does not wedge the run. */
    const DEFAULT_TIMEOUT = 20;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $signPublicKeyPath;

    /** @var string|null */
    private $token;

    /** @var int */
    private $timeout;

    /** @var string */
    private $char = 'smoke';

    /** @var string|null */
    private $publicKey;

    /** @var string */
    private $platform = 'smoke';

    /** @var int */
    private static $lastOnce = 0;

    public function __construct(
        string $baseUrl,
        string $signPublicKeyPath,
        ?string $token = null,
        int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $this->baseUrl           = rtrim($baseUrl, '/');
        $this->signPublicKeyPath = $signPublicKeyPath;
        $this->token             = $token;
        $this->timeout           = $timeout;
    }

    public function withToken(string $token): self
    {
        $clone        = clone $this;
        $clone->token = $token;

        return $clone;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function hasToken(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    /**
     * @param array $query
     */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * @param array $body JSON body, exactly what App\Service\Request::getData()
     *                    will hand to the controllers' validators.
     */
    public function post(string $path, array $body = [], array $query = []): ApiResponse
    {
        return $this->request('POST', $path, ['body' => $body, 'query' => $query]);
    }

    public function put(string $path, array $body = [], array $query = []): ApiResponse
    {
        return $this->request('PUT', $path, ['body' => $body, 'query' => $query]);
    }

    public function patch(string $path, array $body = [], array $query = []): ApiResponse
    {
        return $this->request('PATCH', $path, ['body' => $body, 'query' => $query]);
    }

    public function delete(string $path, array $body = [], array $query = []): ApiResponse
    {
        return $this->request('DELETE', $path, ['body' => $body, 'query' => $query]);
    }

    /**
     * Send a body verbatim, to probe what the application does with one it cannot
     * decode. The MAC still has to match what the server will compute from it,
     * which is why the body is decoded here too.
     */
    public function raw(string $method, string $path, string $rawBody, array $query = []): ApiResponse
    {
        return $this->request($method, $path, ['raw' => $rawBody, 'query' => $query]);
    }

    /**
     * multipart/form-data, for the avatar upload. There is no JSON body, so
     * ParameterCheck sees only $request->request->all(), which is empty unless
     * $fields is set.
     *
     * @param array $files name => absolute path
     */
    public function multipart(string $method, string $path, array $fields, array $files): ApiResponse
    {
        return $this->request($method, $path, ['fields' => $fields, 'files' => $files]);
    }

    /**
     * @param array $options query, body, raw, fields, files, label
     */
    public function request(string $method, string $path, array $options = []): ApiResponse
    {
        $query  = isset($options['query']) ? $options['query'] : [];
        $body   = isset($options['body']) ? $options['body'] : [];
        $raw    = isset($options['raw']) ? $options['raw'] : null;
        $fields = isset($options['fields']) ? $options['fields'] : [];
        $files  = isset($options['files']) ? $options['files'] : [];
        $route  = isset($options['route']) ? $options['route'] : null;
        $label  = isset($options['label']) ? $options['label'] : $method . ' ' . $path;

        $once      = self::nextOnce();
        $time      = time();
        $signature = $this->sign($path, $time, $once);

        // What ParameterCheck::checkParams() will rebuild on the other side.
        if (!empty($files)) {
            $macParams = $fields;
        } elseif ($raw !== null) {
            $decoded   = json_decode($raw, true);
            $macParams = is_array($decoded) ? $decoded : [];
        } else {
            $macParams = $body;
        }

        $macParams['time'] = $time;
        $macParams['once'] = $once;
        ksort($macParams);

        $mac       = str_replace('+', '%20', http_build_query($macParams));
        $parameter = md5($mac . $this->char);

        $headers = [
            'signature: ' . $signature,
            'time: ' . $time,
            'once: ' . $once,
            'platform: ' . $this->platform,
            'parameter: ' . $parameter,
            'Accept: application/json',
        ];

        if ($this->hasToken()) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $url = $this->baseUrl . $this->encodePath($path);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        if (!empty($files)) {
            $post = $fields;
            foreach ($files as $name => $file) {
                // CURLFile sends a real file upload; the leading @ syntax was
                // removed in PHP 7 and would be sent literally.
                $post[$name] = new \CURLFile($file);
            }
            // No Content-Type header here on purpose: curl generates the multipart
            // boundary and sets the header itself. Overriding it with a bare
            // "multipart/form-data" (no boundary) makes the request unparseable.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } elseif ($raw !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
        } elseif ($body) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            // An empty JSON body - deliberately distinct from no body at all,
            // because getData() returns null for the former and [] for the latter.
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        // curl 7.54 on the PHP built-in server: without this a 4xx with a body is
        // still returned, which CURLOPT_RETURNTRANSFER already covers; nothing to
        // configure. Kept explicit for the 3xx case so a redirect is not followed
        // silently and reported as the route's own answer.
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $started = microtime(true);
        $result  = curl_exec($ch);
        $elapsed = microtime(true) - $started;
        $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error   = curl_error($ch);
        curl_close($ch);

        $response = new ApiResponse(
            $method,
            $this->encodePath($path),
            $status,
            $result === false ? '' : (string) $result,
            $error === '' ? null : $error,
            $elapsed
        );

        SmokeReport::record([
            'label'      => $label,
            'route'      => $route,
            'method'     => $method,
            'path'       => $path,
            'url'        => $url,
            'auth'       => $this->hasToken(),
            'status'     => $status,
            'errorCode'  => $response->errorCode(),
            'message'    => $response->message(),
            'envelope'   => $response->isEnvelope(),
            'body'       => mb_substr(preg_replace('/\s+/', ' ', $response->body()), 0, 500),
            'curlError'  => $error === '' ? null : $error,
            'seconds'    => round($elapsed, 3),
        ]);

        return $response;
    }

    /**
     * A once value that is unique and strictly increasing within the process, so
     * Signature::checkOnce() never sees a repeat.
     */
    private static function nextOnce(): int
    {
        $candidate = (int) (microtime(true) * 10000);
        if ($candidate <= self::$lastOnce) {
            $candidate = self::$lastOnce + 1;
        }
        self::$lastOnce = $candidate;

        return $candidate;
    }

    /**
     * Build the `signature` header for one request.
     */
    private function sign(string $path, int $time, int $once): string
    {
        $payload = [
            'time'     => $time,
            'once'     => $once,
            'url'      => $path,
            'platform' => $this->platform,
            'char'     => $this->char,
        ];
        ksort($payload);

        $encrypted = '';
        $ok        = openssl_public_encrypt(http_build_query($payload), $encrypted, $this->publicKey());

        if ($ok === false || $encrypted === '') {
            // The realistic cause is a payload longer than the key can encrypt
            // (RSA with PKCS#1 v1.5 padding leaves key_size - 11 bytes), which
            // fails silently and would otherwise surface as a confusing 401.
            throw new \RuntimeException(sprintf(
                'could not sign the request for %s: openssl_public_encrypt() failed. '
                . 'Check the size of %s - an RSA key can only encrypt (key bytes - 11) bytes at once.',
                $path,
                $this->signPublicKeyPath
            ));
        }

        return base64_encode($encrypted);
    }

    private function publicKey(): string
    {
        if ($this->publicKey === null) {
            if (!is_file($this->signPublicKeyPath)) {
                throw new \RuntimeException(sprintf(
                    'the request signing key is missing: %s. '
                    . 'Generate the pairs with docker/gen-certificates.sh.',
                    $this->signPublicKeyPath
                ));
            }

            $this->publicKey = (string) file_get_contents($this->signPublicKeyPath);
        }

        return $this->publicKey;
    }

    /**
     * Percent-encode a path the way the web server will hand it back as
     * REQUEST_URI, so Signature::checkSign() - which compares against
     * Request::getPathInfo() - sees the same string the client signed.
     */
    private function encodePath(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', $path));

        return implode('/', $segments);
    }
}
