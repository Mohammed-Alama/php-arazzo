<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Normalizer;

use InvalidArgumentException;

/**
 * Hand-rolled, dependency-free `.proto` parser (plan F5.1). This is the
 * DF11/D5 vendored-codec exception: unlike the WSDL parser it never moves to
 * arazzo-sources — protocol-rpc owns `.proto` reading for the same reason the
 * four RPC variants share one package (one consumer, one parser).
 *
 * Covers the subset the slice needs: proto3 syntax header, package,
 * service/rpc declarations (including `stream`), message field declarations
 * (scalars, `repeated`, nested-message fields), and tolerates comments,
 * imports, options, enums, oneofs, maps, and reserved blocks by skipping
 * them. Garbage input throws InvalidArgumentException.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtoParser
{
    /**
     * @var list<array{0: 'ident'|'string'|'sym'|'number', 1: string|int}>
     */
    private array $tokens = [];

    private int $offset = 0;

    public function parse(string $content): ProtoDocument
    {
        $this->tokens = $this->tokenize($content);
        $this->offset = 0;

        $header = $this->expectWord('syntax');
        if ($header !== 'syntax') {
            throw new InvalidArgumentException('Source content is not a proto3 document.');
        }
        $this->expectSymbol('=');
        $version = $this->expectString();
        if ($version !== 'proto3') {
            throw new InvalidArgumentException("Source content announces unsupported syntax '{$version}'; only proto3 is supported.");
        }
        $this->expectSymbol(';');

        $package = '';
        $services = [];
        $messages = [];

        while ($this->offset < count($this->tokens)) {
            $token = $this->tokens[$this->offset];

            if ($token[0] === 'sym') {
                $this->offset++;

                continue;
            }

            $keyword = $token[1];
            if (!in_array($keyword, ['package', 'import', 'option', 'enum', 'message', 'service', 'extend', 'oneof'], true)) {
                $this->skipDeclaration();

                continue;
            }
            $this->offset++;

            match ($keyword) {
                'package' => $package = $this->qualifiedName(),
                'import' => $this->skipToTerminator(';'),
                'option' => $this->skipToTerminator(';'),
                'enum' => $this->skipBlock(),
                'message' => $this->parseMessage($messages),
                'service' => $services[] = $this->parseService(),
                'extend' => $this->skipDeclaration(),
                'oneof' => $this->skipDeclaration(),
            };
        }

        return new ProtoDocument($package, $services, $messages);
    }

    /**
     * @param  array<string, list<ProtoField>>  $messages
     */
    private function parseMessage(array &$messages): void
    {
        $name = $this->expectWord('message-name');
        $this->expectSymbol('{');

        $fields = [];
        while (!$this->atSymbol('}') && $this->offset < count($this->tokens)) {
            $token = $this->peek();

            if ($token[0] === 'sym') {
                $this->offset++;

                continue;
            }

            $keyword = $token[1];
            if (!in_array($keyword, ['repeated', 'map', 'message', 'oneof', 'group', 'extensions', 'reserved', 'option', 'extend'], true)) {
                $fields[] = $this->parseField(repeated: false);

                continue;
            }
            $this->offset++;

            match ($keyword) {
                'repeated' => $fields[] = $this->parseField(repeated: true),
                'map' => $this->skipToTerminator(';'),
                'message', 'oneof', 'group', 'extensions' => $this->skipBlock(),
                'reserved', 'option', 'extend' => $this->skipToTerminator(';'),
            };
        }
        $this->expectSymbol('}');

        $messages[$name] = $fields;
    }

    private function parseField(bool $repeated): ProtoField
    {
        $type = $this->qualifiedName();
        $name = $this->expectWord('field-name');
        $this->expectSymbol('=');
        $number = $this->expectNumber();

        if ($this->atSymbol('[')) {
            $this->offset++;
            $this->skipToTerminator(']');
        }
        $this->expectSymbol(';');

        return new ProtoField($name, $type, $number, $repeated);
    }

    private function parseService(): ProtoService
    {
        $name = $this->expectWord('service-name');
        $this->expectSymbol('{');

        $methods = [];
        while (!$this->atSymbol('}') && $this->offset < count($this->tokens)) {
            $token = $this->peek();

            if ($token[0] === 'sym') {
                $this->offset++;

                continue;
            }

            $keyword = $token[1];
            $this->offset++;

            if ($keyword === 'rpc') {
                $methods[] = $this->parseMethod();
            } else {
                $this->skipToTerminator(';');
            }
        }
        $this->expectSymbol('}');

        return new ProtoService($name, $methods);
    }

    private function parseMethod(): ProtoMethod
    {
        $name = $this->expectWord('method-name');

        $this->expectSymbol('(');
        $requestStreaming = $this->peekWord() === 'stream';
        if ($requestStreaming) {
            $this->offset++;
        }
        $requestType = $this->qualifiedName();
        $this->expectSymbol(')');

        $this->expectWord('returns');
        $this->expectSymbol('(');
        $responseStreaming = $this->peekWord() === 'stream';
        if ($responseStreaming) {
            $this->offset++;
        }
        $responseType = $this->qualifiedName();
        $this->expectSymbol(')');

        if ($this->atSymbol('{')) {
            $this->skipBlock();
        }
        $this->expectSymbol(';');

        return new ProtoMethod($name, $requestType, $responseType, $requestStreaming, $responseStreaming);
    }

    private function qualifiedName(): string
    {
        $name = $this->expectWord('name');
        while ($this->atSymbol('.')) {
            $this->offset++;
            $name .= '.'.$this->expectWord('name-segment');
        }

        return $name;
    }

    /**
     * Consumes a balanced `{ ... }` block including an optional leading name.
     */
    private function skipBlock(): void
    {
        if ($this->peekWord() !== null && !$this->atSymbol('{')) {
            $this->offset++;
        }
        $this->expectSymbol('{');

        $depth = 1;
        while ($this->offset < count($this->tokens) && $depth > 0) {
            $token = $this->tokens[$this->offset++];
            if ($token[0] === 'sym') {
                if ($token[1] === '{') {
                    $depth++;
                } elseif ($token[1] === '}') {
                    $depth--;
                }
            }
        }
    }

    /**
     * Skips a declaration that may be a `;` statement or a `{ ... }` block.
     */
    private function skipDeclaration(): void
    {
        if ($this->atSymbol('{')) {
            $this->skipBlock();

            return;
        }

        $this->skipToTerminator(';');
    }

    /**
     * Advances to the first `$terminal` symbol at brace depth zero.
     */
    private function skipToTerminator(string $terminal): void
    {
        $depth = 0;
        while ($this->offset < count($this->tokens)) {
            $token = $this->tokens[$this->offset++];
            if ($token[0] !== 'sym') {
                continue;
            }
            if ($token[1] === '{') {
                $depth++;
            } elseif ($token[1] === '}') {
                $depth--;
            } elseif ($token[1] === $terminal && $depth === 0) {
                return;
            }
        }
    }

    /**
     * @return array{0: 'ident'|'string'|'sym'|'number', 1: string|int}
     */
    private function peek(): array
    {
        return $this->tokens[$this->offset];
    }

    private function peekWord(): ?string
    {
        $token = $this->tokens[$this->offset] ?? null;
        if ($token === null || $token[0] !== 'ident') {
            return null;
        }

        return (string) $token[1];
    }

    private function atSymbol(string $symbol): bool
    {
        $token = $this->tokens[$this->offset] ?? null;

        return $token !== null && $token[0] === 'sym' && $token[1] === $symbol;
    }

    private function expectWord(string $what): string
    {
        $word = $this->peekWord();
        if ($word === null) {
            throw new InvalidArgumentException("Expected {$what} in .proto source.");
        }
        $this->offset++;

        return $word;
    }

    private function expectSymbol(string $symbol): void
    {
        if (!$this->atSymbol($symbol)) {
            throw new InvalidArgumentException("Expected '{$symbol}' in .proto source.");
        }
        $this->offset++;
    }

    private function expectString(): string
    {
        $token = $this->tokens[$this->offset] ?? null;
        if ($token === null || $token[0] !== 'string') {
            throw new InvalidArgumentException('Expected a string literal in .proto source.');
        }
        $this->offset++;

        return (string) $token[1];
    }

    private function expectNumber(): int
    {
        $token = $this->tokens[$this->offset] ?? null;
        if ($token === null || $token[0] !== 'number') {
            throw new InvalidArgumentException('Expected a field number in .proto source.');
        }
        $this->offset++;

        return (int) $token[1];
    }

    /**
     * @return list<array{0: 'ident'|'string'|'sym'|'number', 1: string|int}>
     */
    private function tokenize(string $content): array
    {
        $tokens = [];
        $length = strlen($content);
        $i = 0;

        while ($i < $length) {
            $char = $content[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if ($char === '/') {
                $afterComment = self::skipComment($content, $i);
                if ($afterComment !== null) {
                    $i = $afterComment;

                    continue;
                }
            }

            if ($char === '"' || $char === "'") {
                $quoted = self::scanQuoted($content, $i);
                $tokens[] = $quoted[0];
                $i = $quoted[1];

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                [$word, $i] = self::scanWord($content, $i);
                $tokens[] = ['ident', $word];

                continue;
            }

            if (ctype_digit($char)) {
                [$digits, $i] = self::scanWord($content, $i);
                $tokens[] = ['number', (int) $digits];

                continue;
            }

            $tokens[] = ['sym', $char];
            $i++;
        }

        return $tokens;
    }

    /**
     * Advances past a line (`//`) or block comment starting at $i, or returns
     * null when the slash-plus-asterisk pair is not a comment opener.
     */
    private static function skipComment(string $content, int $i): ?int
    {
        $next = $content[$i + 1] ?? '';

        if ($next === '/') {
            $break = strpos($content, "\n", $i + 2);

            return $break === false ? strlen($content) : $break + 1;
        }

        if ($next === '*') {
            $break = strpos($content, '*/', $i + 2);

            return $break === false ? strlen($content) : $break + 2;
        }

        return null;
    }

    /**
     * @return array{0: array{0: 'string', 1: string}, 1: int}
     */
    private static function scanQuoted(string $content, int $i): array
    {
        $quote = $content[$i];
        $start = ++$i;
        $length = strlen($content);

        while ($i < $length && $content[$i] !== $quote) {
            $i++;
        }

        return [['string', substr($content, $start, $i - $start)], min($i + 1, $length)];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function scanWord(string $content, int $i): array
    {
        $start = $i;
        $length = strlen($content);

        while ($i < $length && (ctype_alnum($content[$i]) || $content[$i] === '_')) {
            $i++;
        }

        return [substr($content, $start, $i - $start), $i];
    }
}
