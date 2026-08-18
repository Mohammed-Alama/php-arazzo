<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Persistence;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Exceptions\ParserException;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Runner\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\Exceptions\DefinitionHydrationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DatabaseDefinitionRegistry implements DefinitionRegistryInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private Parser $parser,
        private string $tableName = 'arazzo_definitions',
    ) {
    }

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
            return (string) $existingId;
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
        return (string) $this->db->table($this->tableName)
            ->where('document_identity', $identity)
            ->where('content_hash', $contentHash)
            ->value('id');
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        $row = $this->db->table($this->tableName)->where('id', $definitionId)->first();
        if ($row === null) {
            return null;
        }

        $decoded = json_decode((string) $row->raw_document, true);
        if (!is_array($decoded)) {
            throw new DefinitionHydrationException("Definition '{$definitionId}' has unparseable raw_document JSON.");
        }

        try {
            return $this->parser->parse(new RawDocument($decoded, "db://{$definitionId}", Format::Json));
        } catch (ParserException $e) {
            throw new DefinitionHydrationException(
                "Definition '{$definitionId}' no longer passes validation: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function hash(array $raw): string
    {
        $canonical = json_encode($this->sortRecursive($raw));

        return hash('sha256', $canonical === false ? '' : $canonical);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
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
