<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use PHPUnit\Framework\TestCase;

class DefinitionRegistryTest extends TestCase
{
    public function test_registers_and_retrieves_workflow(): void
    {
        $registry = new InMemoryDefinitionRegistry();

        $wf = new Workflow('test_wf', null, null, null, [], [], [], [], [], [], []);

        $id = $registry->register($wf);

        $this->assertNotNull($id);
        $this->assertSame($wf, $registry->get($id));
    }
}
