<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Interfaces\HttpClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var RequestInterface[] */
    public array $requests = [];

    /** @var list<array{response?: ResponseInterface, throwable?: \Throwable}> */
    private array $scripted = [];

    private ResponseInterface $defaultResponse;

    public function __construct(?ResponseInterface $defaultResponse = null)
    {
        $this->defaultResponse = $defaultResponse ?? new Response(200, [], '{}');
    }

    public function enqueue(ResponseInterface $response): void
    {
        $this->scripted[] = ['response' => $response];
    }

    public function failWith(\Throwable $throwable): void
    {
        $this->scripted[] = ['throwable' => $throwable];
    }

    public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->scripted !== []) {
            $entry = array_shift($this->scripted);

            if (isset($entry['throwable'])) {
                throw $entry['throwable'];
            }

            return $entry['response'];
        }

        return $this->defaultResponse;
    }
}
