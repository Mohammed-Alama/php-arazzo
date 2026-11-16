<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\IssueInventory;
use Alama\Tools\IssueIntake\Schema;
use Alama\Tools\IssueIntake\Validator;

function invSchema(): Schema
{
    return Schema::fromFile(dirname(__DIR__, 3).'/config/issues/schema.yaml');
}

it('reports one row per issue with counts and findings', function () {
    $row = IssueInventory::fromGhJson([[
        'number' => 81,
        'title' => 'feat(runner): x',
        'body' => "## 🏗️ Core Metadata & Alignment\n\npkg\n\n## 📝 Problem Statement & Intent\n\nR1. one\n",
        'labels' => [['name' => 'kind:feature', 'package:core', 'priority:normal'], ['name' => 'level:story']],
    ]], new Validator(invSchema()))[0];

    expect($row['number'])->toBe(81)
        ->and($row['kind'])->toBe('kind:feature')
        ->and($row['level'])->toBe('level:story')
        ->and($row['dialect'])->toBe('other')
        ->and($row['requirements'])->toBe(1)
        ->and($row['scenarios'])->toBe(0)
        ->and($row['tests'])->toBe(0)
        ->and($row['permalinks'])->toBe(0)
        ->and($row['permalink_shas_resolve'])->toBe(0)
        ->and($row['missing'])->toContain('feature-scope')
        ->and($row['errors'])->not->toBeEmpty();
});
