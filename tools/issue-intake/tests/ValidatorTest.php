<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Finding;
use Alama\Tools\IssueIntake\Validator;

/** @param  list<Finding>  $findings  @return list<string> */
function codes(array $findings): array
{
    return array_map(static fn (Finding $finding): string => $finding->code, $findings);
}

it('passes a complete bug body', function () {
    $schema = arazzoSchema();

    expect($schema->requiredIdsFor('bug'))->toBe(bugSectionIds());

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds()),
        'labels' => ['bug', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect($findings)->toBe([])
        ->and(Validator::isConforming($findings))->toBeTrue();
});

it('flags an unedited placeholder or bare checklist as empty, not missing', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds(), [
            'problem-statement' => '<!-- explain here -->',
            'acceptance-criteria' => "- [ ]\n- [ ]",
        ]),
        'labels' => ['bug', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe(['empty-section', 'empty-section']);
});

it('treats a written checklist as content', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds(), [
            'acceptance-criteria' => '- [ ] Given a valid document, when I validate, then it passes',
        ]),
        'labels' => ['bug', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe([]);
});

it('reports sections that are absent entirely', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, ['core-metadata']),
        'labels' => ['bug', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe(array_fill(0, 8, 'missing-section'));
});

it('demands exactly one kind label', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds()),
        'labels' => ['level:epic'],
    ]);

    expect(codes($findings))->toContain('missing-kind-label');

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds()),
        'labels' => ['bug', 'enhancement', 'level:epic'],
    ]);

    expect(codes($findings))->toContain('multiple-kind-labels');
});

it('accepts the level declared by the form dropdown when no level label is set', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => "### Level\n\nlevel:epic\n\n".issueBody($schema, bugSectionIds()),
        'labels' => ['bug'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe([]);
});

it('does not accept a body level it cannot resolve', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => "### Level\n\nwhatever\n\n".issueBody($schema, bugSectionIds()),
        'labels' => ['bug'],
    ]);

    expect(codes($findings))->toBe(['missing-level-label']);
});

it('requires a story to sit under an epic', function () {
    $schema = arazzoSchema();

    $body = issueBody($schema, enhancementSectionIds());

    $underEpic = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['enhancement', 'level:user-story'],
        'parent' => 10, 'parentLevel' => 'level:epic',
    ]);

    expect($underEpic)->toBe([]);

    $underStory = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['enhancement', 'level:user-story'],
        'parent' => 10, 'parentLevel' => 'level:user-story',
    ]);

    expect(codes($underStory))->toBe(['parent-level-invalid']);
});

it('requires a story to have a parent at all', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, enhancementSectionIds()),
        'labels' => ['enhancement', 'level:user-story'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing']);
});

it('names a legacy parent that carries no level label', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, enhancementSectionIds()),
        'labels' => ['enhancement', 'level:user-story'],
        'parent' => 42,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing'])
        ->and($findings[0]->message)->toContain('#42');
});

it('refuses to give a sub-task children', function () {
    $schema = arazzoSchema();

    expect($schema->requiredIdsFor('chore'))->toBe(choreSectionIds());

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, choreSectionIds()),
        'labels' => ['chore', 'level:sub-task'],
        'parent' => 7,
        'parentLevel' => 'level:sub-task',
    ]);

    expect(codes($findings))->toBe(['children-forbidden'])
        ->and($findings[0]->message)->toContain('#7');
});

it('treats GitHub\'s no-response sentinel as an empty section', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds(), ['artifacts' => '_No response_']),
        'labels' => ['bug', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe(['empty-section']);
});

it('tolerates a missing parentLevel key', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugSectionIds()),
        'labels' => ['bug', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package-epic',
    ]);

    expect($findings)->toBe([]);
});

it('accepts a package-epic with no kind and no sections', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => "Anything at all — the body is generated.\n",
        'labels' => ['level:package-epic', 'package:runner'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect($findings)->toBe([]);
});

it('demands exactly one package label on a package-epic', function () {
    $schema = arazzoSchema();

    $missing = (new Validator($schema))->validate([
        'body' => '', 'labels' => ['level:package-epic'], 'parent' => null,
    ]);

    expect(codes($missing))->toBe(['missing-package-label']);

    $tooMany = (new Validator($schema))->validate([
        'body' => '', 'labels' => ['level:package-epic', 'package:runner', 'package:runtime'], 'parent' => null,
    ]);

    expect(codes($tooMany))->toBe(['multiple-package-labels']);
});

it('forbids a parent on a package-epic', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => '', 'labels' => ['level:package-epic', 'package:runner'],
        'parent' => 3, 'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe(['parent-forbidden']);
});

it('requires an epic to sit under a package-epic', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => issueBody(arazzoSchema(), bugSectionIds()),
        'labels' => ['bug', 'level:epic'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing']);
});
