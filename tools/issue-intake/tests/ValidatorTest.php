<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Finding;
use Alama\Tools\IssueIntake\PermalinkResolver;
use Alama\Tools\IssueIntake\Validator;

it('passes a complete bugfix body', function () {
    $schema = arazzoSchema();

    expect($schema->requiredIdsFor('kind:bugfix'))->toBe(bugfixSectionIds());

    $findings = (new Validator($schema))->validate([
        'body' => conformingBody($schema, 'kind:bugfix', 1),
        'labels' => ['kind:bugfix', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    expect($findings)->toBe([])
        ->and(Validator::isConforming($findings))->toBeTrue();
});

it('flags an unedited placeholder or bare checklist as empty, not missing', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugfixSectionIds(), [
            'problem-statement' => '<!-- explain here -->',
            'acceptance-criteria' => "- [ ]\n- [ ]",
            'artifacts' => "```json\n{}\n```",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toBe(['empty-section', 'empty-section']);
});

it('treats a written checklist as content', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugfixSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'acceptance-criteria' => '- [ ] Given a valid document, when I validate, then it passes',
            'artifacts' => "```json\n{}\n```",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toBe([]);
});

it('reports sections that are absent entirely', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, ['core-metadata'], ['core-metadata' => 'Package: core · Parent: #1']),
        'labels' => ['kind:bugfix', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    // 8 required sections for bugfix, 1 present (core-metadata); epistemic-matrix
    // missing-section is demoted to warning for epics (D14), so validate() returns 7 errors.
    expect(codes($findings))->toBe(array_fill(0, 7, 'missing-section'));
});

it('demands exactly one kind label', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugfixSectionIds()),
        'labels' => ['level:epic'],
    ]);

    expect(codes($findings))->toContain('missing-kind-label');

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugfixSectionIds()),
        'labels' => ['kind:bugfix', 'kind:feature', 'level:epic'],
    ]);

    expect(codes($findings))->toContain('multiple-kind-labels');
});

it('accepts the level declared by the form dropdown when no level label is set', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => "### Level\n\nlevel:epic\n\n".issueBody($schema, bugfixSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'artifacts' => "```json\n{}\n```",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toBe([]);
});

it('does not accept a body level it cannot resolve', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => "### Level\n\nwhatever\n\n".issueBody($schema, bugfixSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'artifacts' => "```json\n{}\n```",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix'],
    ]);

    expect(codes($findings))->toBe(['missing-level-label']);
});

it('requires a story to sit under an epic', function () {
    $schema = arazzoSchema();

    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one observable requirement.',
        'feature-scope' => "Goals: filled feature-scope\nNon-goals: out of scope",
        'impact-profile' => 'API: none · BC break: no',
        'core-metadata' => 'Package: core · Parent: #10',
    ]);

    $underEpic = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic',
    ]);

    expect($underEpic)->toBe([]);

    $underStory = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:story',
    ]);

    expect(codes($underStory))->toBe(['parent-level-invalid']);
});

it('requires a story to have a parent at all', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, featureSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'feature-scope' => "Goals: filled feature-scope\nNon-goals: out of scope",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core',
        ]),
        'labels' => ['kind:feature', 'level:story'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing']);
});

it('names a legacy parent that carries no level label', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, featureSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'feature-scope' => "Goals: filled feature-scope\nNon-goals: out of scope",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #42',
        ]),
        'labels' => ['kind:feature', 'level:story'],
        'parent' => 42,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing'])
        ->and($findings[0]->message)->toContain('#42');
});

it('refuses to give a sub-task children', function () {
    $schema = arazzoSchema();

    expect($schema->requiredIdsFor('kind:chore'))->toBe(choreSectionIds());

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, choreSectionIds(), [
            'affected-surface' => "Risk: low\nRollback: revert the commit",
            'core-metadata' => 'Package: core · Parent: #7',
        ]),
        'labels' => ['kind:chore', 'level:task'],
        'parent' => 7,
        'parentLevel' => 'level:task',
    ]);

    expect(codes($findings))->toBe(['children-forbidden'])
        ->and($findings[0]->message)->toContain('#7');
});

it('treats GitHub\'s no-response sentinel as an empty section', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugfixSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'artifacts' => '_No response_',
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toBe(['empty-section']);
});

it('tolerates a missing parentLevel key', function () {
    $schema = arazzoSchema();

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, bugfixSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'artifacts' => "```json\n{}\n```",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix', 'level:epic'],
        'parent' => 1,
        'parentLevel' => 'level:package',
    ]);

    expect($findings)->toBe([]);
});

it('accepts a package-epic with no kind and no sections', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => "Anything at all — the body is generated.\n",
        'labels' => ['level:package', 'package:runner'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect($findings)->toBe([]);
});

it('demands exactly one package label on a package-epic', function () {
    $schema = arazzoSchema();

    $missing = (new Validator($schema))->validate([
        'body' => '', 'labels' => ['level:package'], 'parent' => null,
    ]);

    expect(codes($missing))->toBe(['missing-package-label']);

    $tooMany = (new Validator($schema))->validate([
        'body' => '', 'labels' => ['level:package', 'package:runner', 'package:runtime'], 'parent' => null,
    ]);

    expect(codes($tooMany))->toBe(['multiple-package-labels']);
});

it('forbids a parent on a package-epic', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => '', 'labels' => ['level:package', 'package:runner'],
        'parent' => 3, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toBe(['parent-forbidden']);
});

it('requires an epic to sit under a package-epic', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => issueBody(arazzoSchema(), bugfixSectionIds(), [
            'problem-statement' => 'R1. one observable requirement.',
            'artifacts' => "```json\n{}\n```",
            'impact-profile' => 'API: none · BC break: no',
            'core-metadata' => 'Package: core · Parent: #1',
        ]),
        'labels' => ['kind:bugfix', 'level:epic', 'package:core'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing']);
});

it('accepts a conventional prefix that matches the kind', function () {
    $schema = arazzoSchema();
    foreach (['feat(core): add x', 'feat: add x'] as $title) {
        $findings = (new Validator($schema))->validate([
            'title' => $title, 'body' => issueBody($schema, featureSectionIds()),
            'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
        ]);
        expect(codes($findings))->not->toContain('title-kind-mismatch');
    }
});

it('rejects a bracket title and a prefix that belongs to another kind', function () {
    $schema = arazzoSchema();
    foreach (['[Feature] add x', 'fix(core): add x'] as $title) {
        $findings = (new Validator($schema))->validate([
            'title' => $title, 'body' => issueBody($schema, featureSectionIds()),
            'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
        ]);
        expect(codes($findings))->toContain('title-kind-mismatch');
    }
});

it('returns errors from validate() and every finding from report()', function () {
    $schema = arazzoSchema();
    $validator = new Validator($schema);

    $input = [
        'body' => issueBody($schema, featureSectionIds(), ['feature-scope' => 'Goals: x']),
        'labels' => ['kind:feature', 'level:story'],
        'parent' => 10,
        'parentLevel' => 'level:epic',
    ];

    expect(codes($validator->validate($input)))->not->toContain('non-goals-missing')
        ->and(codes($validator->report($input)))->toContain('non-goals-missing');

    expect(Validator::isConforming([new Finding('non-goals-missing', 'x', 'error')]))->toBeFalse()
        ->and(Validator::isConforming([new Finding('missing-section', 'x')]))->toBeFalse();
});

it('requires at least one numbered requirement for feature and bugfix', function () {
    $schema = arazzoSchema();
    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, featureSectionIds(), ['problem-statement' => 'It is broken.']),
        'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('missing-requirement');
});

it('rejects a scenario with two When clauses or a missing Test line', function () {
    $schema = arazzoSchema();
    $text = <<<'MD'
S1 (R1, Reliable): two whens
  Given a step
  When it runs
  When it retries
  Then it throws UnresolvedWorkflowException
  Test: WorkflowEngineTest::it_rejects_unknown

S2 (R1, Safe): no test
  Given a step
  When it runs
  Then it throws UnresolvedWorkflowException
MD;

    $findings = (new Validator($schema))->report([
        'body' => issueBody($schema, featureSectionIds(), ['acceptance-criteria' => $text]),
        'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('scenario-shape')->toContain('scenario-no-test');
});

it('exempts a scenario blocked by a defined decision from scenario-no-test', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one observable requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y\nD1. Should the engine cache? Options: yes, no. Default: no.",
        'acceptance-criteria' => <<<'MD'
S1 (R1, Reliable): blocked by an open decision
  Given a request blocked by D1
  When the cache is consulted
  Then it throws UnresolvedWorkflowException

C1. the cache decision is open
  Check: D1 is open in feature-scope

| Quality | Covered by | Note |
| --- | --- | --- |
| Efficient | S1 | one traversal |
| Flexible | S1 | any key |
| Maintainable | S1 | one scenario per rule |
| Operable | S1 | the exception names the failure |
| Reliable | S1 | throws UnresolvedWorkflowException |
| Safe | C1 | no silent fallback |
| Secure | N/A | no untrusted input surface |
| Suitable | S1 | matches the reported symptom |
| Usable | S1 | the test names the call site |
MD,
        'impact-profile' => 'API: none · BC break: no',
        'core-metadata' => 'Package: core · Parent: #10',
    ]);

    $findings = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic',
    ]);

    expect($findings)->toBe([]);
});

it('flags a scenario blocked by a decision feature-scope does not define', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one observable requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y",
        'acceptance-criteria' => <<<'MD'
S1 (R1, Reliable): blocked by a ghost decision
  Given a request blocked by D9
  When it runs
  Then it throws UnresolvedWorkflowException
MD,
        'impact-profile' => 'API: none · BC break: no',
        'core-metadata' => 'Package: core · Parent: #10',
    ]);

    $input = [
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic',
    ];
    $reported = (new Validator($schema))->report($input);
    $unknown = array_values(array_filter(
        $reported,
        static fn (Finding $f): bool => $f->code === 'blocked-by-unknown-decision',
    ));

    expect($unknown)->toHaveCount(1)
        ->and($unknown[0]->message)->toContain('D9')
        ->and($unknown[0]->severity)->toBe('warning')
        ->and(codes((new Validator($schema))->validate($input)))->not->toContain('blocked-by-unknown-decision');
});

it('still requires a Test line when the blocked-by decision is undefined', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one observable requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y",
        'acceptance-criteria' => <<<'MD'
S1 (R1, Reliable): blocked by a ghost decision, no test
  Given a request blocked by D9
  When it runs
  Then it throws UnresolvedWorkflowException
MD,
        'impact-profile' => 'API: none · BC break: no',
        'core-metadata' => 'Package: core · Parent: #10',
    ]);

    $input = [
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic',
    ];

    expect(codes((new Validator($schema))->validate($input)))->toContain('scenario-no-test');
    expect(codes((new Validator($schema))->report($input)))->toContain('blocked-by-unknown-decision');
});

it('exempts a scenario blocked by a defined decision but warns on an unknown one too', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one observable requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y\nD1. Should the engine cache? Options: yes, no. Default: no.",
        'acceptance-criteria' => <<<'MD'
S1 (R1, Reliable): blocked by one known and one unknown decision
  Given a request blocked by D1 and D9
  When the cache is consulted
  Then it throws UnresolvedWorkflowException
MD,
        'impact-profile' => 'API: none · BC break: no',
        'core-metadata' => 'Package: core · Parent: #10',
    ]);

    $input = [
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic',
    ];

    expect(codes((new Validator($schema))->validate($input)))->not->toContain('scenario-no-test');
    expect(codes((new Validator($schema))->report($input)))->toContain('blocked-by-unknown-decision');
});

it('flags an uncovered requirement and a quality with no coverage', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => "R1. one\nR2. two",
        'acceptance-criteria' => <<<'MD'
S1 (R1, Reliable): covered
  Given a
  When b
  Then it throws DomainException
  Test: ATest::it_x
MD,
    ]);

    $findings = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'],
        'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('requirement-uncovered')
        ->toContain('quality-coverage-missing');
});

it('flags an unmeasurable Then', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one',
        'acceptance-criteria' => "S1 (R1, Reliable): x\n  Given a\n  When b\n  Then it works correctly\n  Test: ATest::it_x",
    ]);

    $findings = (new Validator($schema))->validate([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'],
        'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('vague-then');
});

it('requires every chore checklist item to name a command or a URL', function () {
    $schema = arazzoSchema();
    $text = "- [ ] composer test exits 0\n- [ ] everything looks fine";

    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, choreSectionIds(), ['acceptance-criteria' => $text]),
        'labels' => ['kind:chore', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('checklist-unverifiable');
});

it('accepts T1-T4 and rejects any other tier', function () {
    $schema = arazzoSchema();
    $good = "| Claim | Tier | Evidence | Verification |\n| --- | --- | --- | --- |\n| x | T2 | file | read |";
    $bad = "| Claim | Tier | Evidence | Verification |\n| --- | --- | --- | --- |\n| x | T5 | file | read |";

    $run = fn (string $t) => codes((new Validator($schema))->report([
        'body' => issueBody($schema, featureSectionIds(), ['epistemic-matrix' => $t]),
        'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]));

    expect($run($good))->not->toContain('tier-invalid')
        ->and($run($bad))->toContain('tier-invalid');
});

it('demotes source-grounding and Test-line errors for an epic', function () {
    $schema = arazzoSchema();
    $input = [
        'body' => issueBody($schema, featureSectionIds(), [
            'acceptance-criteria' => "S1 (R1, Reliable): x\n  Given a\n  When b\n  Then it throws DomainException",
        ]),
        'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ];
    $validator = new Validator($schema);

    expect(codes($validator->validate($input)))->not->toContain('scenario-no-test')
        ->and(codes($validator->report($input)))->toContain('scenario-no-test');
});

it('warns rather than errors when an epic omits feature-scope and epistemic-matrix', function () {
    $schema = arazzoSchema();
    $ids = array_values(array_diff(featureSectionIds(), ['feature-scope', 'epistemic-matrix']));
    $input = [
        'body' => issueBody($schema, $ids),
        'labels' => ['kind:feature', 'level:epic', 'package:core'],
        'parent' => 1, 'parentLevel' => 'level:package',
    ];
    $validator = new Validator($schema);

    expect(codes($validator->validate($input)))->not->toContain('missing-section')
        ->and(codes($validator->report($input)))->toContain('missing-section');
});

it('lets an epic with zero or two package labels have no parent', function () {
    $schema = arazzoSchema();
    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, featureSectionIds()),
        'labels' => ['kind:feature', 'level:epic', 'package:a', 'package:b'],
        'parent' => null, 'parentLevel' => null,
    ]);

    expect(codes($findings))->not->toContain('parent-missing');
});

it('still refuses a story with no parent', function () {
    $schema = arazzoSchema();
    $findings = (new Validator($schema))->validate([
        'body' => issueBody($schema, featureSectionIds()),
        'labels' => ['kind:feature', 'level:story'], 'parent' => null, 'parentLevel' => null,
    ]);

    expect(codes($findings))->toContain('parent-missing');
});

it('flags a draft reference in the body as draft-reference warning', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return 100;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => "Observed: something.\nR1. one requirement.",
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L1-L5\nVerified: 2026-11-16",
    ]);
    $body .= "\n\nSee draft 53 for the design.";

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('draft-reference')
        ->and($findings[array_search('draft-reference', codes($findings))]->severity)->toBe('warning');
});

it('passes when no draft reference appears', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return 100;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => "Observed: something.\nR1. one requirement.",
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L1-L5\nVerified: 2026-11-16",
    ]);

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->not->toContain('draft-reference');
});

it('flags a permalink range past the file end as permalink-range-invalid warning', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return 10;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $body = issueBody($schema, featureSectionIds(), [
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L5-L20\nVerified: 2026-11-16",
    ]);

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('permalink-range-invalid')
        ->and($findings[array_search('permalink-range-invalid', codes($findings))]->severity)->toBe('warning');
});

it('passes when the permalink range fits within the file', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return 100;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $body = issueBody($schema, featureSectionIds(), [
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L5-L20\nVerified: 2026-11-16",
    ]);

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->not->toContain('permalink-range-invalid');
});

it('does not flag a permalink range when line count is unverifiable', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return null;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $body = issueBody($schema, featureSectionIds(), [
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L5-L20\nVerified: 2026-11-16",
    ]);

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->not->toContain('permalink-range-invalid');
});

it('flags a problem statement intro over 150 words as problem-statement-too-long warning', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return 100;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $intro = implode(' ', array_fill(0, 160, 'word'));
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => $intro."\n\nR1. one requirement.",
    ]);

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('problem-statement-too-long')
        ->and($findings[array_search('problem-statement-too-long', codes($findings))]->severity)->toBe('warning');
});

it('passes when the problem statement intro is under 150 words', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return true;
        }

        public function lineCount(string $sha, string $path): ?int
        {
            return 100;
        }

        public function isKnown(string $sha): bool
        {
            return true;
        }

        public function isAncestor(string $sha): bool
        {
            return true;
        }
    };

    $intro = implode(' ', array_fill(0, 100, 'word'));
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => $intro."\n\nR1. one requirement.",
    ]);

    $findings = (new Validator($schema, $resolver))->report([
        'body' => $body, 'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->not->toContain('problem-statement-too-long');
});

it('treats a decision line ending with Decided: as closed (no open-decision blocker)', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y\nD1. cache? Options: yes, no. Default: yes. Decided: yes.",
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L1-L5\nVerified: 2026-11-16",
        'core-metadata' => 'Package: core · Parent: #10',
        'impact-profile' => 'API: none · BC break: no',
    ]);

    $result = (new Validator($schema))->readiness([
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic', 'readyForAgent' => true,
    ]);

    expect($result['ready'])->toBeTrue()
        ->and($result['blockers'])->not->toContain('open-decision');
});

it('still treats an unresolved decision line as open-decision', function () {
    $schema = arazzoSchema();
    $body = issueBody($schema, featureSectionIds(), [
        'problem-statement' => 'R1. one requirement.',
        'feature-scope' => "Goals: x\nNon-goals: y\nD1. cache? Options: yes, no. Default: yes.",
        'source-grounding' => "- claim\nhttps://github.com/o/r/blob/".str_repeat('a', 40)."/x.php#L1-L5\nVerified: 2026-11-16",
        'core-metadata' => 'Package: core · Parent: #10',
        'impact-profile' => 'API: none · BC break: no',
    ]);

    $result = (new Validator($schema))->readiness([
        'body' => $body, 'labels' => ['kind:feature', 'level:story'],
        'parent' => 10, 'parentLevel' => 'level:epic', 'readyForAgent' => true,
    ]);

    expect($result['ready'])->toBeFalse()
        ->and($result['blockers'])->toContain('open-decision');
});
