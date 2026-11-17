<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Sync;

use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Runner\Sync\WorkflowDependencyResolver;

function dependencyWorkflow(string $id, array $dependsOn = []): Workflow
{
    return new Workflow($id, null, null, null, $dependsOn, [], [], [], [], []);
}

it('orders transitive dependencies in execution order and excludes the target', function () {
    $resolver = new WorkflowDependencyResolver([
        dependencyWorkflow('a'),
        dependencyWorkflow('b', ['a']),
        dependencyWorkflow('c', ['b', 'a']),
        dependencyWorkflow('d', ['c']),
    ]);

    expect($resolver->orderFor('d'))->toBe(['a', 'b', 'c']);
});

it('records each dependency once across a diamond graph', function () {
    $resolver = new WorkflowDependencyResolver([
        dependencyWorkflow('base'),
        dependencyWorkflow('left', ['base']),
        dependencyWorkflow('right', ['base']),
        dependencyWorkflow('target', ['left', 'right']),
    ]);

    expect($resolver->orderFor('target'))->toBe(['base', 'left', 'right']);
});

it('guards against cycles without duplicating preflight validation', function () {
    $resolver = new WorkflowDependencyResolver([
        dependencyWorkflow('a', ['b']),
        dependencyWorkflow('b', ['a']),
    ]);

    expect($resolver->orderFor('a'))->toBe(['b']);
});

it('returns an empty plan for a workflow without dependencies', function () {
    $resolver = new WorkflowDependencyResolver([dependencyWorkflow('leaf')]);

    expect($resolver->orderFor('leaf'))->toBe([]);
});

it('does not leak memoization between queries', function () {
    $resolver = new WorkflowDependencyResolver([
        dependencyWorkflow('a'),
        dependencyWorkflow('x', ['a']),
        dependencyWorkflow('b'),
        dependencyWorkflow('y', ['b']),
    ]);

    expect($resolver->orderFor('x'))->toBe(['a'])
        ->and($resolver->orderFor('y'))->toBe(['b']);
});

it('tolerates a declared dependency that is missing from the workflow list', function () {
    $resolver = new WorkflowDependencyResolver([dependencyWorkflow('target', ['ghost'])]);

    expect($resolver->orderFor('target'))->toBe(['ghost']);
});
