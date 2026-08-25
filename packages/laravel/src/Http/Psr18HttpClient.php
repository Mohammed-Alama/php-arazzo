<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Http;

use Alama\Arazzo\Runner\Execution\Contracts\HttpClientInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18HttpClient implements HttpClientInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
    {
        // PSR-18 cannot express per-request timeouts; delegate to Guzzle's
        // request() API so declared step timeouts are actually enforced.
        if ($timeoutSeconds !== null) {
            // Guzzle's options stub requires non-empty header value lists.
            $headers = [];

            foreach ($request->getHeaders() as $name => $values) {
                $headers[$name] = $values === [] ? [''] : array_values(array_map(strval(...), $values));
            }

            return $this->client->request(
                $request->getMethod(),
                (string) $request->getUri(),
                [
                    'headers' => $headers,
                    'body' => (string) $request->getBody(),
                    'connect_timeout' => 5.0,
                    'timeout' => $timeoutSeconds,
                ],
            );
        }

        return $this->client->sendRequest($request);
    }
}
