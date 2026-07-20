<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Generator\Clients;

use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class OpenAiClient implements AiClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private string $apiKey,
        private string $endpoint,
        private string $model = 'gpt-4o'
    ) {}

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $payload = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.0,
        ]);

        $request = $this->requestFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Content-Type', 'application/json');

        $body = fopen('php://temp', 'r+');
        if ($body !== false) {
            fwrite($body, $payload);
            rewind($body);

            $stream = new class($body) implements StreamInterface {
                /** @var resource */
                private $resource;
                
                public function __construct($resource) {
                    $this->resource = $resource;
                }
                
                public function __toString(): string {
                    try {
                        $this->rewind();
                        return $this->getContents();
                    } catch (\Throwable $e) {
                        return '';
                    }
                }
                
                public function close(): void {
                    if (is_resource($this->resource)) {
                        fclose($this->resource);
                    }
                }
                
                public function detach() {
                    $res = $this->resource;
                    $this->resource = null;
                    return $res;
                }
                
                public function getSize(): ?int {
                    if (!is_resource($this->resource)) return null;
                    $stats = fstat($this->resource);
                    return $stats['size'] ?? null;
                }
                
                public function tell(): int {
                    if (!is_resource($this->resource)) throw new RuntimeException('Stream detached');
                    $result = ftell($this->resource);
                    if ($result === false) throw new RuntimeException('Unable to tell');
                    return $result;
                }
                
                public function eof(): bool {
                    if (!is_resource($this->resource)) return true;
                    return feof($this->resource);
                }
                
                public function isSeekable(): bool {
                    if (!is_resource($this->resource)) return false;
                    $meta = stream_get_meta_data($this->resource);
                    return $meta['seekable'];
                }
                
                public function seek($offset, $whence = \SEEK_SET): void {
                    if (!is_resource($this->resource)) throw new RuntimeException('Stream detached');
                    if (!$this->isSeekable()) throw new RuntimeException('Stream not seekable');
                    if (fseek($this->resource, $offset, $whence) === -1) {
                        throw new RuntimeException('Unable to seek');
                    }
                }
                
                public function rewind(): void {
                    $this->seek(0);
                }
                
                public function isWritable(): bool {
                    if (!is_resource($this->resource)) return false;
                    $meta = stream_get_meta_data($this->resource);
                    $mode = $meta['mode'];
                    return strpbrk($mode, 'xwca+') !== false;
                }
                
                public function write($string): int {
                    if (!is_resource($this->resource)) throw new RuntimeException('Stream detached');
                    if (!$this->isWritable()) throw new RuntimeException('Stream not writable');
                    $result = fwrite($this->resource, $string);
                    if ($result === false) throw new RuntimeException('Unable to write');
                    return $result;
                }
                
                public function isReadable(): bool {
                    if (!is_resource($this->resource)) return false;
                    $meta = stream_get_meta_data($this->resource);
                    $mode = $meta['mode'];
                    return strpbrk($mode, 'r+') !== false;
                }
                
                public function read($length): string {
                    if (!is_resource($this->resource)) throw new RuntimeException('Stream detached');
                    if (!$this->isReadable()) throw new RuntimeException('Stream not readable');
                    $result = fread($this->resource, $length);
                    if ($result === false) throw new RuntimeException('Unable to read');
                    return $result;
                }
                
                public function getContents(): string {
                    if (!is_resource($this->resource)) throw new RuntimeException('Stream detached');
                    $result = stream_get_contents($this->resource);
                    if ($result === false) throw new RuntimeException('Unable to get contents');
                    return $result;
                }
                
                public function getMetadata($key = null) {
                    if (!is_resource($this->resource)) return $key ? null : [];
                    $meta = stream_get_meta_data($this->resource);
                    if ($key === null) return $meta;
                    return $meta[$key] ?? null;
                }
            };
            
            $request = $request->withBody($stream);
        }

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('API Error: ' . $response->getReasonPhrase());
        }

        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
