<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\SectionParser;

it('matches headings despite emoji, numbering and case', function () {
    $sections = SectionParser::parse("### 🧬 7. Source Grounding & Code Location\ncommit abc123\n");

    expect($sections[SectionParser::normalise('🧬 7. Source Grounding & Code Location')])
        ->toBe(['commit abc123']);
});

it('normalises the schema heading and the form label to the same key', function () {
    expect(SectionParser::normalise('🏗️ 1. Core Metadata & Alignment'))
        ->toBe(SectionParser::normalise('Core Metadata & Alignment'));
});

it('ignores headings inside fenced code blocks', function () {
    $body = "### Problem Statement\n```yaml\n# Not A Section\n## Neither Is This\n```\nreal content";

    expect(array_keys(SectionParser::parse($body)))
        ->toBe([SectionParser::normalise('Problem Statement')]);
});

it('keeps every occurrence of a duplicated heading', function () {
    $sections = SectionParser::parse("### Notes\n\n### Notes\nsecond body");

    expect($sections[SectionParser::normalise('Notes')])->toBe(['', 'second body']);
});
