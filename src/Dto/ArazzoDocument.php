<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

final readonly class ArazzoDocument
{
    /**
     * @param list<SourceDescription> $sourceDescriptions
     * @param list<Workflow> $workflows
     * @param array<string,mixed> $specificationExtensions
     * @param array<string,mixed>|null $rawRoot
     */
    public function __construct(
        public string $arazzo,
        public Info $info,
        public array $sourceDescriptions,
        public array $workflows,
        public Components $components,
        public array $specificationExtensions,
        public ?array $rawRoot = null,
    ) {
    }
}
