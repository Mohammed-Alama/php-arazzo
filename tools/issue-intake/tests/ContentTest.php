<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Content;
use Alama\Tools\IssueIntake\SectionParser;

it('reads every occurrence of a repeated section', function () {
    $parsed = SectionParser::parse("## 📝 Problem Statement & Intent\n\nR1. one\n\n## 📝 Problem Statement & Intent\n\nR2. two\n");
    $schema = arazzoSchema();

    expect(Content::sectionText($parsed, $schema, 'problem-statement'))
        ->toContain('R1. one')->toContain('R2. two');
});

it('returns an empty string for an absent section', function () {
    $parsed = SectionParser::parse("## 📝 Problem Statement & Intent\n\nR1. one\n");
    $schema = arazzoSchema();

    expect(Content::sectionText($parsed, $schema, 'source-grounding'))->toBe('');
});

it('does not open a section inside a fenced block', function () {
    $parsed = SectionParser::parse("## 📝 Problem Statement & Intent\n\n```markdown\n## 🧬 Source Grounding & Code Location\n```\n");

    expect($parsed)->toHaveKey(SectionParser::normalise('Problem Statement & Intent'))
        ->and($parsed)->not->toHaveKey(SectionParser::normalise('Source Grounding & Code Location'));
});

it('matches line-anchored ids in a CRLF body', function () {
    expect(Content::requirementIds("R1. one\r\nR2. two\r\n"))->toHaveKeys(['R1', 'R2']);
});

it('splits scenarios and reads their header refs', function () {
    $blocks = Content::headingBlocks("S1 (R1, Reliable): x\n  Given a\n  When b\n  Then c\nS2 (R2, Safe): y\n");

    expect($blocks)->toHaveCount(2)
        ->and(Content::scenarioHeader($blocks[1]))->toBe(['id' => 'S2', 'refs' => ['R2'], 'quality' => 'Safe']);
});

it('returns null when a block has no scenario header', function () {
    expect(Content::scenarioHeader("Just some text\n"))->toBeNull();
});

it('returns every fenced block without the fence lines', function () {
    $text = "intro\n```json\n{\"a\":1}\n```\nmid\n```\nplain\n```\n";

    expect(Content::fencedBlocks($text))->toBe(['{"a":1}', 'plain']);
});

it('returns bare github permalinks from trimmed lines', function () {
    $text = "- claim\n"
        ."https://github.com/Mohammed-Alama/php-arazzo/blob/0123456789abcdef0123456789abcdef01234567/tools/issue-intake/src/Validator.php#L1-L10\n"
        ."not a permalink\n"
        ."  https://github.com/Mohammed-Alama/php-arazzo/blob/abcdef0123456789abcdef0123456789abcdef01/README.md  \n";

    expect(Content::barePermalinks($text))->toHaveCount(2);
});

it('splits text into lines with trailing carriage returns removed', function () {
    expect(Content::lines("a\r\nb\r\nc\nd"))->toBe(['a', 'b', 'c', 'd']);
});

it('returns checklist items', function () {
    expect(Content::checklistItems("- [x] done\n- [ ] open\nplain\n* [x] also done"))
        ->toBe(['- [x] done', '- [ ] open', '* [x] also done']);
});
