<?php

namespace App\Tests\Support;

/**
 * One HTTP response from the running application, shaped the way the smoke tests
 * need to look at it.
 *
 * The application answers with its own JSON envelope -
 * {"message": ..., "errorCode": ..., "data": ..., "requestUrl": ...} - for both
 * success and failure, because App\EventSubscriber\Exception renders every
 * App\Exception\Base that way. Anything that is NOT that envelope came from
 * somewhere the application does not control: PHP's error output, the web
 * server, or Symfony's own error page.
 *
 * That distinction is the whole point. See isEnvelope().
 */
final class ApiResponse
{
    /** @var string */
    private $method;

    /** @var string */
    private $path;

    /** @var int */
    private $status;

    /** @var string */
    private $body;

    /** @var string|null curl_error(), or null when the transfer finished */
    private $transportError;

    /** @var float */
    private $seconds;

    /** @var array|null */
    private $decoded;

    /** @var bool */
    private $decodedDone = false;

    public function __construct(
        string $method,
        string $path,
        int $status,
        string $body,
        ?string $transportError = null,
        float $seconds = 0.0
    ) {
        $this->method         = $method;
        $this->path           = $path;
        $this->status         = $status;
        $this->body           = $body;
        $this->transportError = $transportError;
        $this->seconds        = $seconds;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function transportError(): ?string
    {
        return $this->transportError;
    }

    public function seconds(): float
    {
        return $this->seconds;
    }

    /**
     * The decoded body, or null when it is not a JSON object/array.
     *
     * @return array|null
     */
    public function json(): ?array
    {
        if (false === $this->decodedDone) {
            $this->decodedDone = true;
            $decoded           = json_decode($this->body, true);
            $this->decoded     = is_array($decoded) ? $decoded : null;
        }

        return $this->decoded;
    }

    /**
     * The application's own error code, or null when the body is not its envelope.
     *
     * array_key_exists rather than isset: errorCode 0 is the success value.
     *
     * @return int|null
     */
    public function errorCode(): ?int
    {
        $json = $this->json();

        if ($json === null || !array_key_exists('errorCode', $json)) {
            return null;
        }

        return (int) $json['errorCode'];
    }

    public function message(): string
    {
        $json = $this->json();

        if ($json === null || !array_key_exists('message', $json)) {
            return '';
        }

        return (string) $json['message'];
    }

    /**
     * @return array
     */
    public function data(): array
    {
        $json = $this->json();

        if ($json === null || !array_key_exists('data', $json) || !is_array($json['data'])) {
            return [];
        }

        return $json['data'];
    }

    /**
     * Did the application itself produce this response?
     *
     * True only for its own envelope. A PHP fatal error, a Symfony error page or
     * an empty body all fail this, which is what makes it a real oracle: a route
     * can answer HTTP 200 with a fatal error in the body (observed - see
     * tests/Functional/RouteSmokeTest.php), and "status < 500" alone would call
     * that a pass.
     */
    public function isEnvelope(): bool
    {
        $json = $this->json();

        return $json !== null
            && array_key_exists('message', $json)
            && array_key_exists('errorCode', $json);
    }

    /**
     * True when the body carries PHP error output rather than an HTTP response
     * the application meant to send.
     */
    public function looksLikeRawPhpError(): bool
    {
        return stripos($this->body, 'Fatal error') !== false
            || stripos($this->body, 'Parse error') !== false
            || stripos($this->body, 'Warning:') !== false
            || stripos($this->body, '<br />') !== false
            || stripos($this->body, 'Stack trace:') !== false;
    }

    /**
     * A one-line description for assertion messages, truncated so a large body
     * does not bury the rest of the failure output.
     */
    public function describe(): string
    {
        $body = preg_replace('/\s+/', ' ', $this->body);
        if (mb_strlen($body) > 400) {
            $body = mb_substr($body, 0, 400) . '...';
        }

        return sprintf(
            '%s %s -> HTTP %d%s%s',
            $this->method,
            $this->path,
            $this->status,
            $this->transportError === null ? '' : ' transport=' . $this->transportError,
            $body === '' ? ' (empty body)' : ' ' . $body
        );
    }
}
