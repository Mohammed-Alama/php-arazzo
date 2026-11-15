<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\PermalinkResolver;
use Alama\Tools\IssueIntake\Validator;

it('rejects a branch link and a missing path at a known SHA', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return $path === 'exists.php';
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
    $sha = str_repeat('a', 40);
    $text = "- claim\nhttps://github.com/o/r/blob/main/a.php#L1-L2\n"
        ."- claim\nhttps://github.com/o/r/blob/{$sha}/gone.php#L1-L2\n";

    $findings = (new Validator($schema, $resolver))->validate([
        'body' => issueBody($schema, featureSectionIds(), ['source-grounding' => $text]),
        'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($findings))->toContain('permalink-invalid');
});

it('warns instead of erroring when the SHA is not in the clone', function () {
    $schema = arazzoSchema();
    $resolver = new class() implements PermalinkResolver
    {
        public function pathExistsAt(string $sha, string $path): bool
        {
            return false;
        }

        public function isKnown(string $sha): bool
        {
            return false;
        }

        public function isAncestor(string $sha): bool
        {
            return false;
        }
    };
    $sha = str_repeat('b', 40);

    $report = (new Validator($schema, $resolver))->report([
        'body' => issueBody($schema, featureSectionIds(), [
            'source-grounding' => "- claim\nhttps://github.com/o/r/blob/{$sha}/a.php#L1-L2\nVerified: 2026-11-15",
        ]),
        'labels' => ['kind:feature', 'level:epic'], 'parent' => 1, 'parentLevel' => 'level:package',
    ]);

    expect(codes($report))->toContain('permalink-unverifiable');
});
