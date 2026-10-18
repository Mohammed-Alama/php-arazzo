<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Exceptions\ParserException;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Runtime\State\Exceptions\DefinitionHydrationException;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class DatabaseDefinitionRegistry implements DefinitionRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private Parser $parser,
        private string $tableName = 'arazzo_definitions',
    ) {}

    public function register(ArazzoDocument $document): string
    {
        $raw = $document->rawRoot;
        if ($raw === null) {
            throw new InvalidArgumentException('Cannot register an ArazzoDocument with no rawRoot.');
        }

        $identity = $document->info->title;
        $contentHash = $this->hash($raw);

        $existingId = $this->db->table($this->tableName)
            ->where('document_identity', $identity)
            ->where('content_hash', $contentHash)
            ->value('id');

        if ($existingId !== null) {
            return $this->textColumn($existingId);
        }

        $id = (string) Str::ulid();

        $this->db->table($this->tableName)->insertOrIgnore([
            'id' => $id,
            'document_identity' => $identity,
            'content_hash' => $contentHash,
            'raw_document' => json_encode($raw),
            'created_at' => now(),
        ]);

        // A concurrent register() may have won the race; re-select rather than trust $id.
        return $this->textColumn($this->db->table($this->tableName)
            ->where('document_identity', $identity)
            ->where('content_hash', $contentHash)
            ->value('id'));
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        $row = $this->db->table($this->tableName)->where('id', $definitionId)->first();
        if ($row === null) {
            return null;
        }

        $decoded = json_decode($this->textColumn($row->raw_document), true);
        if (!is_array($decoded)) {
            throw new DefinitionHydrationException("Definition '{$definitionId}' has unparseable raw_document JSON.");
        }

        try {
            return $this->parser->parse(new RawDocument($this->stringKeyedDocument($decoded), "db://{$definitionId}", Format::Json));
        } catch (ParserException $e) {
            throw new DefinitionHydrationException(
                "Definition '{$definitionId}' no longer passes validation: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Laravel types query-builder column values as mixed. Every column this
     * registry reads holds text; anything else means a corrupt row rather
     * than a document we can register or hydrate.
     */
    private function textColumn(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new DefinitionHydrationException('Expected a scalar column value, got '.get_debug_type($value).'.');
        }

        return (string) $value;
    }

    /**
     * RawDocument models the decoded document as a name-to-value map. JSON
     * object keys are strings, but PHP turns the numeric ones ("0") into ints;
     * they are restored rather than dropped, leaving it to the parser to
     * decide whether such a key is a legal Arazzo field.
     *
     * @param  array<array-key, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function stringKeyedDocument(array $decoded): array
    {
        $document = [];
        foreach ($decoded as $key => $value) {
            $document[(string) $key] = $value;
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function hash(array $raw): string
    {
        $canonical = json_encode($this->sortRecursive($raw));

        return hash('sha256', $canonical === false ? '' : $canonical);
    }

    /**
     * Keys are irrelevant to canonical hashing; a document nests lists, so the
     * recursion cannot assume string keys.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function sortRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortRecursive($value);
            }
        }

        return $data;
    }
}
