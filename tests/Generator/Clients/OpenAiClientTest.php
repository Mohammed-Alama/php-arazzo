<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Generator\Clients;

use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

it('sends prompt to openai and returns content', function () {
    $requestFactory = new class implements RequestFactoryInterface {
        public function createRequest(string $method, $uri): RequestInterface {
            return new class($method, (string)$uri) implements RequestInterface {
                private array $headers = [];
                public function __construct(public string $method, public string $uri) {}
                public function getProtocolVersion(): string { return '1.1'; }
                public function withProtocolVersion($version): RequestInterface { return $this; }
                public function getHeaders(): array { return $this->headers; }
                public function hasHeader($name): bool { return isset($this->headers[$name]); }
                public function getHeader($name): array { return $this->headers[$name] ?? []; }
                public function getHeaderLine($name): string { return implode(', ', $this->getHeader($name)); }
                public function withHeader($name, $value): RequestInterface { $c = clone $this; $c->headers[$name] = (array)$value; return $c; }
                public function withAddedHeader($name, $value): RequestInterface { return $this; }
                public function withoutHeader($name): RequestInterface { return $this; }
                public function getBody(): StreamInterface { throw new \Exception(); }
                public function withBody(StreamInterface $body): RequestInterface { return $this; }
                public function getRequestTarget(): string { return ''; }
                public function withRequestTarget($requestTarget): RequestInterface { return $this; }
                public function getMethod(): string { return $this->method; }
                public function withMethod($method): RequestInterface { return $this; }
                public function getUri(): \Psr\Http\Message\UriInterface { throw new \Exception(); }
                public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false): RequestInterface { return $this; }
            };
        }
    };

    $responseMock = new class implements ResponseInterface {
        public function getStatusCode(): int { return 200; }
        public function withStatus($code, $reasonPhrase = ''): ResponseInterface { return $this; }
        public function getReasonPhrase(): string { return 'OK'; }
        public function getProtocolVersion(): string { return '1.1'; }
        public function withProtocolVersion($version): ResponseInterface { return $this; }
        public function getHeaders(): array { return []; }
        public function hasHeader($name): bool { return false; }
        public function getHeader($name): array { return []; }
        public function getHeaderLine($name): string { return ''; }
        public function withHeader($name, $value): ResponseInterface { return $this; }
        public function withAddedHeader($name, $value): ResponseInterface { return $this; }
        public function withoutHeader($name): ResponseInterface { return $this; }
        public function getBody(): StreamInterface {
            return new class implements StreamInterface {
                public function __toString(): string { 
                    return json_encode([
                        'choices' => [
                            ['message' => ['content' => 'generated_yaml']]
                        ]
                    ]); 
                }
                public function close(): void {}
                public function detach() {}
                public function getSize(): ?int { return null; }
                public function tell(): int { return 0; }
                public function eof(): bool { return true; }
                public function isSeekable(): bool { return false; }
                public function seek($offset, $whence = \SEEK_SET): void {}
                public function rewind(): void {}
                public function isWritable(): bool { return false; }
                public function write($string): int { return 0; }
                public function isReadable(): bool { return true; }
                public function read($length): string { return ''; }
                public function getContents(): string { return $this->__toString(); }
                public function getMetadata($key = null) { return null; }
            };
        }
        public function withBody(StreamInterface $body): ResponseInterface { return $this; }
    };

    $httpClient = new class($responseMock) implements ClientInterface {
        public array $requests = [];
        public function __construct(private ResponseInterface $response) {}
        public function sendRequest(RequestInterface $request): ResponseInterface {
            $this->requests[] = $request;
            return $this->response;
        }
    };

    $client = new OpenAiClient($httpClient, $requestFactory, 'test-key', 'gpt-4o');
    $result = $client->generate('system_instructions', 'user_trace');

    expect($result)->toBe('generated_yaml');
    expect($httpClient->requests)->toHaveCount(1);
    
    /** @var RequestInterface $req */
    $req = $httpClient->requests[0];
    expect($req->getMethod())->toBe('POST');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer test-key');
});
