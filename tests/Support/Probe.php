<?php

namespace App\Tests\Support;

/**
 * Sends one entry from Routes::probes(), resolving its {placeholders} first.
 */
final class Probe
{
    public static function send(ApiClient $client, array $probe): ApiResponse
    {
        $method = $probe['method'];
        $path   = Context::substitute($probe['path']);
        $query  = Context::substitute(isset($probe['query']) ? $probe['query'] : []);

        $options = [
            'query' => $query,
            'route' => $probe['route'],
            'label' => $probe['id'] . ($client->hasToken() ? ' [as ' . self::identity($probe) . ']' : ' [anonymous]'),
        ];

        if (isset($probe['files'])) {
            $options['files'] = Context::substitute($probe['files']);
        } elseif (isset($probe['raw'])) {
            $options['raw'] = $probe['raw'];
        } elseif (isset($probe['body'])) {
            $options['body'] = Context::substitute($probe['body']);
        }

        return $client->request($method, $path, $options);
    }

    private static function identity(array $probe): string
    {
        return isset($probe['as']) ? (string) $probe['as'] : 'anonymous';
    }
}
