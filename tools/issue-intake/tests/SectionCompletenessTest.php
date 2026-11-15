<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Validator;

/** @param  list<\Alama\Tools\IssueIntake\Finding>  $findings  @return list<string> */
function contentCodes(string $kind, array $ids, array $content, int $parent = 10): array
{
    $schema = arazzoSchema();

    return codes((new Validator($schema))->validate([
        'body' => issueBody($schema, $ids, $content),
        'labels' => [$kind, 'level:epic'], 'parent' => $parent, 'parentLevel' => 'level:package',
    ]));
}

it('flags a feature scope with no non-goals', function () {
    expect(contentCodes('kind:feature', featureSectionIds(), ['feature-scope' => 'Goals: x']))
        ->toContain('non-goals-missing');
});

it('flags an affected surface with no rollback line', function () {
    expect(contentCodes('kind:chore', choreSectionIds(), ['affected-surface' => 'Risk: low']))
        ->toContain('rollback-missing');
});

it('flags an artifacts section with no fenced block', function () {
    expect(contentCodes('kind:bugfix', bugfixSectionIds(), ['artifacts' => 'see the log']))
        ->toContain('artifact-block-missing');
});

it('flags an impact profile with no BC line', function () {
    expect(contentCodes('kind:feature', featureSectionIds(), ['impact-profile' => 'API: none']))
        ->toContain('bc-line-missing');
});

it('flags a core metadata parent that disagrees with the link', function () {
    expect(contentCodes('kind:feature', featureSectionIds(), ['core-metadata' => 'Parent: #99'], 10))
        ->toContain('parent-mismatch');
});
