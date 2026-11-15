<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Validator;

it('is not ready while a design decision is open, even when conforming', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'feature-scope' => "Goals: x\nNon-goals: never\nD1. storage? Options: A, B. Default: A.",
    ]);

    $result = (new Validator($schema))->readiness([
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic', 'readyForAgent' => true,
    ]);

    expect($result['ready'])->toBeFalse()
        ->and($result['blockers'])->toContain('open-decision');
});
