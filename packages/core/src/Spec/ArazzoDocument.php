<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Enum\SpecVersion;

final readonly class ArazzoDocument
{
    /**
     * @param  list<SourceDescription>  $sourceDescriptions
     * @param  list<Workflow>  $workflows
     * @param  array<string,mixed>  $specificationExtensions
     * @param  array<string,mixed>|null  $rawRoot
     */
    public function __construct(
        public string $arazzo,
        public Info $info,
        public array $sourceDescriptions,
        public array $workflows,
        public Components $components,
        public array $specificationExtensions,
        public ?array $rawRoot = null,
        public SpecVersion $specVersion = SpecVersion::V1_0,
        public ?string $self = null,
    ) {}

    public function hasExternalSourceFor(string $workflowId): bool
    {
        foreach ($this->sourceDescriptions as $sourceDesc) {
            if ($sourceDesc->type === SourceType::Arazzo && str_starts_with($workflowId, $sourceDesc->name.'.')) {
                return true;
            }
        }

        return false;
    }
}
