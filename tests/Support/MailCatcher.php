<?php

namespace App\Tests\Support;

/**
 * Reads verification codes back out of a MailHog instance.
 *
 * The registration and password-change flows both mail a six-digit code, and the
 * application stores it only in the cache - it is never returned in the response,
 * which is the point. So the only way to exercise those flows end to end is to
 * watch the mail. MailHog is already part of docker-compose.yml, and CI runs the
 * same image as a service.
 *
 * MailHog's /api/v2/messages returns the message with its raw multipart body and
 * quoted-printable encoding intact, so the body is split to the text/plain part
 * before decoding: the Message-ID and multipart boundary also contain digit runs,
 * and searching them would eventually return a wrong "code".
 */
final class MailCatcher
{
    /**
     * The newest six-digit code mailed to $email, or a RuntimeException if
     * nothing arrives in time.
     *
     * $since is a unix timestamp from just before the code was requested, and
     * messages older than it are ignored. Without that filter this returns
     * whatever the address was last mailed, which on a second run against the same
     * MailHog and the same address is an old, already-consumed code - and the
     * failure it produces ("验证码错误") looks nothing like the cause. MailHog
     * timestamps to the nanosecond, so a one-second slack is plenty for clock
     * skew. Pass null only when deliberately reusing a code that is already live.
     */
    public static function codeFor(string $baseUrl, string $email, ?int $since = null, float $timeout = 10.0): string
    {
        $deadline = microtime(true) + $timeout;
        $last     = '';

        while (microtime(true) < $deadline) {
            foreach (self::messages($baseUrl) as $item) {
                if (!self::isAddressedTo($item, $email)) {
                    continue;
                }

                if ($since !== null && !self::isNewerThan($item, $since)) {
                    continue;
                }

                $body    = isset($item['Content']['Body']) ? (string) $item['Content']['Body'] : '';
                $decoded = quoted_printable_decode(self::textPart($body));

                if (preg_match('/(?<!\d)(\d{6})(?!\d)/', $decoded, $match) === 1) {
                    return $match[1];
                }

                $last = $decoded;
            }

            usleep(200000);
        }

        throw new \RuntimeException(sprintf(
            'no verification code for %s arrived at %s within %.0fs%s. Last matching body: %s',
            $email,
            $baseUrl,
            $timeout,
            $since === null ? '' : sprintf(' (only messages newer than %d counted)', $since),
            $last === '' ? '(none)' : mb_substr($last, 0, 300)
        ));
    }

    /**
     * Was this message created at or after $since?
     *
     * @param array $item
     */
    private static function isNewerThan(array $item, int $since): bool
    {
        if (!isset($item['Created'])) {
            return true;
        }

        $created = strtotime((string) $item['Created']);

        if ($created === false) {
            return true;
        }

        return $created >= $since - 1;
    }

    /**
     * @return array
     */
    private static function messages(string $baseUrl): array
    {
        $ch = curl_init(rtrim($baseUrl, '/') . '/api/v2/messages?limit=50');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException(sprintf('could not reach MailHog at %s: %s', $baseUrl, $error));
        }

        $decoded = json_decode((string) $result, true);
        if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
            throw new \RuntimeException(sprintf('unexpected MailHog response from %s', $baseUrl));
        }

        return $decoded['items'];
    }

    /**
     * MailHog reports recipients under Content.Headers.To. It is normally a list
     * but a single recipient can come back as a plain string.
     *
     * @param array $item
     */
    private static function isAddressedTo(array $item, string $email): bool
    {
        if (!isset($item['Content']['Headers']['To'])) {
            return false;
        }

        $to = $item['Content']['Headers']['To'];
        if (!is_array($to)) {
            $to = [$to];
        }

        foreach ($to as $recipient) {
            if (stripos((string) $recipient, $email) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The content of the text/plain section of a MIME body.
     *
     * Everything before the part's content is dropped: the boundary line, the
     * part headers, and the blank line between them. Those are not decoration. A
     * Symfony boundary looks like
     * "_=_symfony_1791481275_313829673944ba884687d9aa9f02f10d_=_" - the hex part
     * contains 6-digit runs bounded by hex letters, so "884687" is a perfectly good
     * match for the code pattern and sits several lines above the real code. That
     * was not a hypothetical: it is what this did before the headers were stripped,
     * and it only went unnoticed because most boundaries happen not to contain a
     * run of exactly six digits.
     *
     * Note it cannot key off the word "multipart/": that lives in the message
     * headers, which MailHog reports separately, not in the body.
     */
    private static function textPart(string $body): string
    {
        $parts = preg_split('/\r?\n--/', $body);

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (preg_match('#Content-Type:\s*text/plain#i', self::headersOf($part)) === 1) {
                    return self::afterHeaders($part);
                }
            }
        }

        // Not multipart, or no text/plain part: fall back to treating whatever is
        // before the first blank line as headers.
        return self::afterHeaders($body);
    }

    /**
     * The header block of a MIME part: everything before the first blank line.
     */
    private static function headersOf(string $part): string
    {
        $split = preg_split("/\r?\n\r?\n/", $part, 2);

        return isset($split[1]) ? $split[0] : $part;
    }

    /**
     * Everything after the blank line that ends a MIME part's headers.
     */
    private static function afterHeaders(string $part): string
    {
        $split = preg_split("/\r?\n\r?\n/", $part, 2);

        return isset($split[1]) ? $split[1] : $part;
    }
}
