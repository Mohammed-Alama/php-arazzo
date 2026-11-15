<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use DateTimeImmutable;

/**
 * Validates an issue body, its labels and its level links against the schema.
 *
 * This is the *one* engine (spec D12): the gh shim, the CI gate and the
 * file-issue skill all reach their verdict through this class. Validation is
 * pure — no network, no filesystem — so every caller feeds it the same shape.
 */
final class Validator
{
    /** GitHub renders this for a form field the reporter left blank. */
    private const NO_RESPONSE = '_No response_';

    private const QUALITIES = ['Efficient','Flexible','Maintainable','Operable','Reliable','Safe','Secure','Suitable','Usable'];

    public function __construct(private readonly Schema $schema, private readonly ?PermalinkResolver $resolver = null) {}

    /**
     * All findings whose severity is `error` — the conformance verdict.
     *
     * @param  array{
     *     body?: string,
     *     labels?: list<string>,
     *     title?: string,
     *     parent?: int|null,
     *     parentLevel?: string|null,
     * }  $input
     * @return list<Finding>
     */
    public function validate(array $input): array
    {
        return array_values(array_filter(
            $this->allFindings($input),
            static fn (Finding $finding): bool => $finding->severity === 'error',
        ));
    }

    /**
     * Agent readiness: whether an issue is safe to hand to an agent, evaluated
     * separately from conformance. An issue can conform yet still be blocked
     * — an open design decision, a T4 tier, a non-agent-ready level, stale
     * grounding, or a broken parent chain.
     *
     * Readiness reports — it never mutates labels. The gate decides what to
     * do with the blockers.
     *
     * Blocker codes: `open-decision`, `tier-unverified`,
     * `level-not-agent-ready`, `stale-grounding`, `parent-chain-invalid`.
     *
     * @param  array{
     *     body?: string,
     *     labels?: list<string>,
     *     title?: string,
     *     parent?: int|null,
     *     parentLevel?: string|null,
     *     readyForAgent?: bool,
     * }  $input
     * @return array{ready: bool, blockers: list<string>}
     */
    public function readiness(array $input): array
    {
        $body = $input['body'] ?? '';
        $parsed = SectionParser::parse($body);

        $blockers = [];

        if ($this->hasOpenDecision($parsed)) {
            $blockers[] = 'open-decision';
        }

        if ($this->hasUnverifiedTier($parsed)) {
            $blockers[] = 'tier-unverified';
        }

        $level = $this->readinessLevel($input);

        if ($level !== 'level:story' && $level !== 'level:task') {
            $blockers[] = 'level-not-agent-ready';
        }

        if ($this->isStaleGrounding($body)) {
            $blockers[] = 'stale-grounding';
        }

        // Any conformance error breaks the parent chain the agent would build on.
        if ($this->validate($input) !== []) {
            $blockers[] = 'parent-chain-invalid';
        }

        return ['ready' => $blockers === [], 'blockers' => $blockers];
    }

    /**
     * open-decision: the feature-scope names an unresolved design decision,
     * a line-anchored `D<n>. `.
     */
    private function hasOpenDecision(array $parsed): bool
    {
        $key = SectionParser::normalise($this->schema->section('feature-scope')['heading']);

        foreach (Content::lines(implode("\n\n", $parsed[$key] ?? [])) as $line) {
            if (preg_match('/^D\d+\./', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * tier-unverified: any epistemic-matrix data row carries tier T4 — a
     * valid tier, but not one an agent may act on without verification.
     */
    private function hasUnverifiedTier(array $parsed): bool
    {
        $key = SectionParser::normalise($this->schema->section('epistemic-matrix')['heading']);

        if (!array_key_exists($key, $parsed)) {
            return false;
        }

        $started = false;

        foreach (Content::lines(implode("\n\n", $parsed[$key])) as $line) {
            if (!$started) {
                $started = true;
                continue;
            }

            if (preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $line) === 1) {
                continue;
            }

            $cells = explode('|', $line);

            if (trim($cells[2] ?? '') === 'T4') {
                return true;
            }
        }

        return false;
    }

    /**
     * The level the issue resolves to, without recording any finding —
     * readiness reports, it does not validate.
     *
     * @return string|null
     */
    private function readinessLevel(array $input): ?string
    {
        $labels = self::strings($input['labels'] ?? []);
        $levels = array_keys($this->schema->levels());

        $declared = array_values(array_intersect($labels, $levels));

        if ($declared !== []) {
            return $declared[0];
        }

        $parsed = SectionParser::parse($input['body'] ?? '');

        foreach ($parsed[SectionParser::normalise('Level')] ?? [] as $block) {
            $candidate = trim($block);

            if (in_array($candidate, $levels, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * stale-grounding: the `Verified:` date is older than 14 days and no
     * commit-pinned permalink names a SHA that is an ancestor of HEAD.
     *
     * With no resolver injected, freshness falls back to the date alone: an
     * old `Verified:` is stale by itself.
     */
    private function isStaleGrounding(string $body): bool
    {
        $verified = null;

        foreach (Content::lines($body) as $line) {
            if (preg_match('/^Verified:\s*(\d{4}-\d{2}-\d{2})/', trim($line), $match) === 1) {
                $verified = $match[1];
            }
        }

        if ($verified === null) {
            return false;
        }

        $verifiedAt = DateTimeImmutable::createFromFormat('Y-m-d', $verified);

        if ($verifiedAt === false) {
            return false;
        }

        if ($verifiedAt->diff(new DateTimeImmutable())->days <= 14) {
            return false;
        }

        if ($this->resolver !== null) {
            foreach ($this->groundingShas($body) as $sha) {
                if ($this->resolver->isAncestor($sha)) {
                    return false;
                }
            }

            return true;
        }

        // No resolver: freshness falls back to the date alone.
        return true;
    }

    /**
     * The commit SHAs pinned by the bare permalinks in the body.
     *
     * @return list<string>
     */
    private function groundingShas(string $body): array
    {
        $shas = [];

        foreach (Content::barePermalinks($body) as $url) {
            if (preg_match('/blob\/([0-9a-f]{40})/', $url, $match) === 1) {
                $shas[] = $match[1];
            }
        }

        return $shas;
    }

    /**
     * Every finding, errors first while preserving discovery order within
     * each severity — the full report the gate and inventory consume.
     *
     * @param  array{
     *     body?: string,
     *     labels?: list<string>,
     *     title?: string,
     *     parent?: int|null,
     *     parentLevel?: string|null,
     * }  $input
     * @return list<Finding>
     */
    public function report(array $input): array
    {
        $findings = $this->allFindings($input);

        usort($findings, static fn (Finding $a, Finding $b): int => [
            'error' => 0,
            'warning' => 1,
        ][$a->severity] <=> [
            'error' => 0,
            'warning' => 1,
        ][$b->severity]);

        return $findings;
    }

    /**
     * True when no finding carries `error` severity — warnings are allowed.
     *
     * @param  list<Finding>  $findings
     */
    public static function isConforming(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding->severity === 'error') {
                return false;
            }
        }

        return true;
    }

    /**
     * Every finding the engine produces, in discovery order, before any
     * severity filtering or re-sorting.
     *
     * @param  array{
     *     body?: string,
     *     labels?: list<string>,
     *     title?: string,
     *     parent?: int|null,
     *     parentLevel?: string|null,
     * }  $input
     * @return list<Finding>
     */
    private function allFindings(array $input): array
    {
        $body = $input['body'] ?? '';
        $labels = self::strings($input['labels'] ?? []);
        $title = $input['title'] ?? '';
        $parent = $input['parent'] ?? null;
        $parentLevel = $input['parentLevel'] ?? null;

        $parsed = SectionParser::parse($body);
        $findings = [];

        $level = $this->resolveLevel($labels, $parsed, $findings);
        $kind = $this->resolveKind($labels, $findings, !$this->kindForbidden($level));

        if ($kind !== null && !$this->sectionsExempt($level)) {
            $this->checkSections($kind, $parsed, $findings);
            $this->checkSectionContent($kind, $parsed, $parent, $findings);
            $this->checkPermalinks($parsed, $findings);
        }

        if ($kind !== null && $title !== '') {
            $this->checkTitle($title, $kind, $findings);
        }

        if ($level !== null) {
            $this->checkLevel($level, $parent, $parentLevel, $findings);
            $this->checkPackageLabel($level, $labels, $findings);
        }

        return $findings;
    }

    /**
     * Runs the per-section content checks declared in `Schema::checks()`:
     * `non-goals-missing`, `rollback-missing`, `artifact-block-missing`,
     * `bc-line-missing` and `parent-mismatch`, plus the acceptance-criteria
     * checks (scenario shape, requirement/quality coverage, vague Thens)
     * implemented in earlier tasks. Severity comes from the schema,
     * defaulting to `error`. A section that is blank or still holds its
     * placeholder is already reported by `checkSections()`, so the
     * completeness checks below only run on sections that hold real content.
     *
     * @param  list<Finding>  $findings
     */
    private function checkSectionContent(string $kind, array $parsed, ?int $parent, array &$findings): void
    {
        $severities = $this->schema->checks();

        if ($kind === 'kind:feature') {
            $this->checkNonGoals($parsed, $severities, $findings);

            $acKey = SectionParser::normalise($this->schema->section('acceptance-criteria')['heading']);
            $acText = implode("\n\n", $parsed[$acKey] ?? []);

            $this->checkScenarioShape($acText, $findings);
            $this->checkRequirementCoverage($acText, Content::sectionText($parsed, $this->schema, 'problem-statement'), $findings);
            $this->checkQualityCoverage($acText, $findings);
            $this->checkVagueThen($acText, $findings);

            $problemKey = SectionParser::normalise($this->schema->section('problem-statement')['heading']);

            // A missing or placeholder section is already reported by checkSections();
            // only a present, non-empty statement without a numbered requirement
            // gets missing-requirement.
            if (array_key_exists($problemKey, $parsed)
                && $this->hasContent($parsed[$problemKey], $this->schema->section('problem-statement')['placeholder'])
                && Content::requirementIds(Content::sectionText($parsed, $this->schema, 'problem-statement')) === []) {
                $findings[] = new Finding(
                    'missing-requirement',
                    'Problem statement must name at least one numbered requirement: "R1. <one sentence>".',
                    $severities['missing-requirement'] ?? 'error',
                    'problem-statement',
                );
            }
        }

        if (in_array($kind, ['kind:feature', 'kind:bugfix'], true)) {
            $this->checkBcLine($parsed, $severities, $findings);
        }

        if (in_array($kind, ['kind:chore', 'kind:release'], true)) {
            $acKey = SectionParser::normalise($this->schema->section('acceptance-criteria')['heading']);
            $acText = implode("\n\n", $parsed[$acKey] ?? []);

            $this->checkChecklistItems($acText, $findings);
        }

        $this->checkRollback($parsed, $severities, $findings);
        $this->checkArtifacts($parsed, $severities, $findings);
        $this->checkParentMismatch($parent, $parsed, $severities, $findings);

        $matrixKey = SectionParser::normalise($this->schema->section('epistemic-matrix')['heading']);

        if (array_key_exists($matrixKey, $parsed)) {
            $this->checkTierValidity(
                Content::sectionText($parsed, $this->schema, 'epistemic-matrix'),
                $findings,
                $severities['tier-invalid'] ?? 'error',
            );
        }
    }

    /**
     * non-goals-missing: `feature-scope` must contain `Non-goals:` with text
     * after it on the same line, or a non-empty bullet list under a bare
     * `Non-goals:` header that runs until the next `Key:`-style line.
     * Feature only.
     *
     * @param  list<Finding>  $findings
     */
    private function checkNonGoals(array $parsed, array $severities, array &$findings): void
    {
        $key = SectionParser::normalise($this->schema->section('feature-scope')['heading']);

        if (!array_key_exists($key, $parsed)
            || !$this->hasContent($parsed[$key], $this->schema->section('feature-scope')['placeholder'])) {
            return;
        }

        if ($this->satisfiesNonGoals(Content::sectionText($parsed, $this->schema, 'feature-scope'))) {
            return;
        }

        $findings[] = new Finding(
            'non-goals-missing',
            'Feature scope must name its non-goals: add `Non-goals: <line>` with text after it, '
            .'or a non-empty bullet list under a bare `Non-goals:` before the next `Key:` line.',
            $severities['non-goals-missing'] ?? 'error',
            'feature-scope',
        );
    }

    /**
     * True when `Non-goals:` carries text on the same line, or heads a
     * non-empty bullet list (blank lines allowed) before anything that is
     * not a bullet ends it.
     */
    private static function satisfiesNonGoals(string $text): bool
    {
        $lines = Content::lines($text);

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if (preg_match('/^Non-goals:\s*\S/u', $line) === 1) {
                return true;
            }

            if (preg_match('/^Non-goals:\s*$/u', $line) !== 1) {
                continue;
            }

            // Bare header: the first non-blank line after it must open the
            // bullet list; anything else (a `Key:` line, a paragraph) ends it.
            for ($j = $index + 1; $j < count($lines); $j++) {
                $follow = trim($lines[$j]);

                if ($follow === '') {
                    continue;
                }

                return preg_match('/^[-*+]\s+\S/u', $follow) === 1;
            }
        }

        return false;
    }

    /**
     * rollback-missing: `affected-surface` must contain `Rollback:` with at
     * least one word after it. Runs whenever the section holds content.
     *
     * @param  list<Finding>  $findings
     */
    private function checkRollback(array $parsed, array $severities, array &$findings): void
    {
        $key = SectionParser::normalise($this->schema->section('affected-surface')['heading']);

        if (!array_key_exists($key, $parsed)
            || !$this->hasContent($parsed[$key], $this->schema->section('affected-surface')['placeholder'])) {
            return;
        }

        foreach (Content::lines(Content::sectionText($parsed, $this->schema, 'affected-surface')) as $raw) {
            if (preg_match('/^Rollback:\s+\S/u', trim($raw)) === 1) {
                return;
            }
        }

        $findings[] = new Finding(
            'rollback-missing',
            'Affected surface must name a rollback: add a `Rollback: <step>` line with at least one word after it.',
            $severities['rollback-missing'] ?? 'error',
            'affected-surface',
        );
    }

    /**
     * artifact-block-missing: `artifacts` must hold at least one fenced
     * triple-backtick block. Runs whenever the section holds content.
     *
     * @param  list<Finding>  $findings
     */
    private function checkArtifacts(array $parsed, array $severities, array &$findings): void
    {
        $key = SectionParser::normalise($this->schema->section('artifacts')['heading']);

        if (!array_key_exists($key, $parsed)
            || !$this->hasContent($parsed[$key], $this->schema->section('artifacts')['placeholder'])
            || Content::fencedBlocks(Content::sectionText($parsed, $this->schema, 'artifacts')) !== []) {
            return;
        }

        $findings[] = new Finding(
            'artifact-block-missing',
            'Artifacts must include a fenced block: the minimal document, request/response or stack trace in triple backticks.',
            $severities['artifact-block-missing'] ?? 'error',
            'artifacts',
        );
    }

    /**
     * bc-line-missing: `impact-profile` must match `BC break: yes|no`.
     * Feature and bugfix only.
     *
     * @param  list<Finding>  $findings
     */
    private function checkBcLine(array $parsed, array $severities, array &$findings): void
    {
        $key = SectionParser::normalise($this->schema->section('impact-profile')['heading']);

        if (!array_key_exists($key, $parsed)
            || !$this->hasContent($parsed[$key], $this->schema->section('impact-profile')['placeholder'])) {
            return;
        }

        if (preg_match('/BC break:\s*(yes|no)/i', Content::sectionText($parsed, $this->schema, 'impact-profile')) === 1) {
            return;
        }

        $findings[] = new Finding(
            'bc-line-missing',
            'Impact profile must state whether this is a BC break: `BC break: yes` or `BC break: no`.',
            $severities['bc-line-missing'] ?? 'error',
            'impact-profile',
        );
    }

    /**
     * parent-mismatch: when the issue links a parent, `core-metadata` must
     * name the same `Parent: #n`; a missing line is also a mismatch.
     *
     * @param  list<Finding>  $findings
     */
    private function checkParentMismatch(?int $parent, array $parsed, array $severities, array &$findings): void
    {
        if ($parent === null) {
            return;
        }

        $key = SectionParser::normalise($this->schema->section('core-metadata')['heading']);

        if (!array_key_exists($key, $parsed)
            || !$this->hasContent($parsed[$key], $this->schema->section('core-metadata')['placeholder'])) {
            return;
        }

        $named = null;

        if (preg_match('/Parent:\s*#(\d+)/u', Content::sectionText($parsed, $this->schema, 'core-metadata'), $match) === 1) {
            $named = (int) $match[1];
        }

        if ($named === $parent) {
            return;
        }

        $findings[] = new Finding(
            'parent-mismatch',
            $named === null
                ? sprintf('core-metadata names no parent — add `Parent: #%d` to match the linked parent.', $parent)
                : sprintf('core-metadata names Parent: #%d but the issue is linked to parent #%d.', $named, $parent),
            $severities['parent-mismatch'] ?? 'error',
            'core-metadata',
        );
    }

    /**
     * Permalink verification on the source-grounding section (spec §7): every
     * line mentioning `blob/` must be a bare, commit-pinned URL; bare URLs
     * must be known to the clone and name a path that exists at that SHA.
     * Runs only when a resolver was injected — without one the engine stays
     * pure and reports nothing here.
     *
     * @param  list<Finding>  $findings
     */
    private function checkPermalinks(array $parsed, array &$findings): void
    {
        if ($this->resolver === null) {
            return;
        }

        $severities = $this->schema->checks();
        $key = SectionParser::normalise($this->schema->section('source-grounding')['heading']);

        if (!array_key_exists($key, $parsed)) {
            return;
        }

        $text = self::stripWrappers(implode("\n\n", $parsed[$key]));

        $hasBare = Content::barePermalinks($text) !== [];

        if (! $hasBare) {
            $findings[] = new Finding(
                'source-grounding-empty',
                'Source grounding names no bare commit-pinned permalink — add one per claim: '
                .'https://github.com/<owner>/<repo>/blob/<40-hex-sha>/<path>#L<start>-L<end>',
                $severities['source-grounding-empty'] ?? 'error',
                'source-grounding',
            );
        }

        foreach (Content::lines($text) as $line) {
            if (str_contains($line, 'blob/') === false) {
                continue;
            }

            $trimmed = trim($line);

            // A wrapped permalink ([label](url), <a href>, text then URL) is
            // not bare — it fails as not-bare, never as a bare permalink.
            if (preg_match('/^https?:\/\/\S+$/', $trimmed) !== 1) {
                $findings[] = new Finding(
                    'permalink-not-bare',
                    'Permalink is not a bare URL: "'.$trimmed.'" — paste the URL on its own line.',
                    $severities['permalink-not-bare'] ?? 'error',
                    'source-grounding',
                );

                continue;
            }

            if (preg_match('~^https://github\.com/[^/]+/[^/]+/blob/([0-9a-f]{40})/([^#]+)#L(\d+)-L(\d+)$~', $trimmed, $match) !== 1) {
                $findings[] = new Finding(
                    'permalink-invalid',
                    'Permalink is not pinned to a commit SHA with a line range: "'.$trimmed.'"',
                    $severities['permalink-invalid'] ?? 'error',
                    'source-grounding',
                );

                continue;
            }

            $sha = $match[1];
            $path = $match[2];

            if (!$this->resolver->isKnown($sha)) {
                $findings[] = new Finding(
                    'permalink-unverifiable',
                    "Commit {$sha} is not in this clone — the permalink cannot be verified.",
                    $severities['permalink-unverifiable'] ?? 'warning',
                    'source-grounding',
                );

                continue;
            }

            if (!$this->resolver->pathExistsAt($sha, $path)) {
                $findings[] = new Finding(
                    'permalink-invalid',
                    "Path \"{$path}\" does not exist at {$sha} — the permalink points at nothing.",
                    $severities['permalink-invalid'] ?? 'error',
                    'source-grounding',
                );
            }
        }
    }

    /**
     * Strips `<details>`/`<summary>` wrapper tags while keeping their inner
     * content, so a wrapped permalink still reads as a non-bare line instead
     * of disappearing from the check entirely.
     */
    private static function stripWrappers(string $text): string
    {
        return preg_replace('/<\/?(details|summary)(\s[^>]*)?>/i', '', $text);
    }

    /**
     * checklist-unverifiable: every `- [ ]` / `- [x]` line in a chore or
     * release acceptance criteria must name a backticked command/token or a
     * `https://` URL — otherwise one finding per item, quoting the item text.
     * Applies to kind:chore and kind:release only.
     *
     * @param  list<Finding>  $findings
     */
    private function checkChecklistItems(string $text, array &$findings): void
    {
        $severities = $this->schema->checks();

        foreach (Content::checklistItems($text) as $item) {
            if (preg_match('/`[^`]+`/', $item) === 1 || preg_match('/https?:\/\//', $item) === 1) {
                continue;
            }

            $findings[] = new Finding(
                'checklist-unverifiable',
                'Checklist item is unverifiable: "'.$item.'" — name a backticked command or link to a URL.',
                $severities['checklist-unverifiable'] ?? 'error',
                'acceptance-criteria',
            );
        }
    }

    /**
     * tier-invalid: every data row of the epistemic-matrix table must carry a
     * tier cell (the second cell) drawn from T1–T4. The header row and any
     * separator row are skipped; every other row yields one finding when its
     * tier cell is missing or outside T1–T4. Applies when epistemic-matrix is
     * required or present — an absent required section is already reported by
     * checkSections().
     *
     * @param  list<Finding>  $findings
     */
    private function checkTierValidity(string $text, array &$findings, string $severity): void
    {
        $lines = Content::lines($text);
        $started = false;

        foreach ($lines as $line) {
            // The first table line is the header — never a data row.
            if (!$started) {
                $started = true;
                continue;
            }

            // Separator rows (`| --- | --- |`) carry no tier.
            if (preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $line) === 1) {
                continue;
            }

            $cells = explode('|', $line);
            $tier = trim($cells[2] ?? '');

            if (preg_match('/^T[1-4]$/', $tier) !== 1) {
                $findings[] = new Finding(
                    'tier-invalid',
                    'Epistemic matrix row names no T1–T4 tier: "'.$line.'" — the second cell must be T1, T2, T3 or T4.',
                    $severity,
                    'epistemic-matrix',
                );
            }
        }
    }

    /**
     * Each `S<n>` scenario block in the acceptance criteria must be Given →
     * When → Then: at least one `Given`, exactly one `When`, at least one
     * `Then`, first occurrences in that order — otherwise `scenario-shape`,
     * naming the `S<n>`. Every block must also carry a `Test:
     * ClassTest::it_does_x` line — otherwise `scenario-no-test`.
     * Applies to feature and bugfix only; a chore/release acceptance section
     * is a checklist (Task 8).
     *
     * @param  list<Finding>  $findings
     */
    private function checkScenarioShape(string $text, array &$findings): void
    {
        $severities = $this->schema->checks();

        foreach (Content::headingBlocks($text) as $block) {
            $header = Content::scenarioHeader($block);

            // Free-form acceptance criteria (no `S<n>` scenario header) is not
            // a scenario block — nothing to shape-check here.
            if ($header === null) {
                continue;
            }

            $scenario = $header['id'];

            $given = $when = $then = 0;
            $givenAt = $whenAt = $thenAt = null;

            foreach (Content::lines($block) as $index => $line) {
                if (preg_match('/^\s*Given\b/', $line) === 1) {
                    $givenAt ??= $index;
                    $given++;
                } elseif (preg_match('/^\s*When\b/', $line) === 1) {
                    $whenAt ??= $index;
                    $when++;
                } elseif (preg_match('/^\s*Then\b/', $line) === 1) {
                    $thenAt ??= $index;
                    $then++;
                }
            }

            $shapeOk = $given >= 1 && $when === 1 && $then >= 1
                && $givenAt !== null && $whenAt !== null && $thenAt !== null
                && $givenAt < $whenAt && $whenAt < $thenAt;

            if (!$shapeOk) {
                $findings[] = new Finding(
                    'scenario-shape',
                    "Scenario {$scenario} breaks the Given → When → Then shape: "
                    ."at least one Given, exactly one When, at least one Then, in that order.",
                    $severities['scenario-shape'] ?? 'error',
                    'acceptance-criteria',
                );
            }

            if (preg_match('/^\s*Test:\s*\S+::\S+\s*$/m', $block) !== 1) {
                $findings[] = new Finding(
                    'scenario-no-test',
                    "Scenario {$scenario} names no Test: line — add \"Test: ClassTest::it_does_x\".",
                    $severities['scenario-no-test'] ?? 'error',
                    'acceptance-criteria',
                );
            }
        }
    }

    /**
     * requirement-uncovered: every R<n> named in the problem statement must be
     * referenced by at least one S# scenario header. One finding lists every
     * uncovered requirement id.
     *
     * @param  list<Finding>  $findings
     */
    private function checkRequirementCoverage(string $acText, string $problemText, array &$findings): void
    {
        $severities = $this->schema->checks();

        if (!$this->hasScenarios($acText)) {
            return;
        }

        $required = array_keys(Content::requirementIds($problemText));

        $covered = [];
        foreach (Content::headingBlocks($acText) as $block) {
            $header = Content::scenarioHeader($block);

            if ($header === null) {
                continue;
            }

            foreach ($header['refs'] as $ref) {
                $covered[$ref] = true;
            }
        }

        $uncovered = array_values(array_filter(
            $required,
            static fn (string $id): bool => !isset($covered[$id]),
        ));

        if ($uncovered !== []) {
            $findings[] = new Finding(
                'requirement-uncovered',
                'Requirement(s) not referenced by any S# scenario: ' . implode(', ', $uncovered),
                $severities['requirement-uncovered'] ?? 'error',
                'acceptance-criteria',
            );
        }
    }

    /**
     * True when the acceptance criteria holds at least one `S<n>` scenario
     * block. Free-form criteria (a checklist, a paragraph) carries no
     * scenario coverage to verify.
     */
    private function hasScenarios(string $acText): bool
    {
        foreach (Content::headingBlocks($acText) as $block) {
            if (Content::scenarioHeader($block) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * quality-coverage-missing: the | Quality | Covered by | Note | table must
     * name each of the nine qualities in its first cell. One finding lists
     * every quality with no coverage row.
     *
     * @param  list<Finding>  $findings
     */
    private function checkQualityCoverage(string $acText, array &$findings): void
    {
        $severities = $this->schema->checks();

        if (!$this->hasScenarios($acText)) {
            return;
        }

        $present = [];

        foreach (Content::lines($acText) as $line) {
            if (preg_match('/^\s*\|\s*([^|]+?)\s*\|\s*[^|]*?\s*\|\s*[^|]*?\s*\|?\s*$/', $line, $m) !== 1) {
                continue;
            }

            if (in_array(trim($m[1]), self::QUALITIES, true)) {
                $present[trim($m[1])] = true;
            }
        }

        $missing = array_values(array_filter(
            self::QUALITIES,
            static fn (string $q): bool => !isset($present[$q]),
        ));

        if ($missing !== []) {
            $findings[] = new Finding(
                'quality-coverage-missing',
                'Quality coverage table is missing these qualities: ' . implode(', ', $missing),
                $severities['quality-coverage-missing'] ?? 'error',
                'acceptance-criteria',
            );
        }
    }

    /**
     * vague-then: a Then/And step under a Then that uses a vague qualifier
     * (works, as expected, correctly, gracefully, properly) must also name a
     * concrete artifact (class, Exception, backtick span, number, return,
     * event, count). One finding per vague step.
     *
     * @param  list<Finding>  $findings
     */
    private function checkVagueThen(string $acText, array &$findings): void
    {
        $severities = $this->schema->checks();

        foreach (Content::headingBlocks($acText) as $block) {
            $inThen = false;

            foreach (Content::lines($block) as $line) {
                if (preg_match('/^\s*Given\b/', $line) === 1 || preg_match('/^\s*When\b/', $line) === 1) {
                    $inThen = false;

                    continue;
                }

                if (preg_match('/^\s*Then\b/', $line) === 1) {
                    $inThen = true;
                } elseif (preg_match('/^\s*And\b/', $line) === 1) {
                    if (!$inThen) {
                        continue;
                    }
                } else {
                    continue;
                }

                if (preg_match('/\bworks\b|as expected|\bcorrectly\b|\bgracefully\b|\bproperly\b/', $line) !== 1) {
                    continue;
                }

                if (preg_match('/::class|\b[A-Z]\w+Exception\b|`[^`]+`|\b\d+\b|\breturns? \S+|\bevent\b|\bcount\b/', $line) === 1) {
                    continue;
                }

                $findings[] = new Finding(
                    'vague-then',
                    'Vague Then/And step names no concrete outcome: ' . trim($line),
                    $severities['vague-then'] ?? 'error',
                    'acceptance-criteria',
                );
            }
        }
    }

    /**
     * title-kind-mismatch (Schema::checks()): when the issue declares a kind
     * and a title, the title must open with one of that kind's conventional
     * prefixes (`feat(core):`, `fix:`, …). The prefix is parsed as
     * `^(prefix)(\(|!?:)`. Severity comes from the schema, defaulting to
     * `error`.
     *
     * @param  list<Finding>  $findings
     */
    private function checkTitle(string $title, string $kind, array &$findings): void
    {
        $severities = $this->schema->checks();
        $allowed = self::allowedPrefixes($kind);
        $prefix = self::parsePrefix($title, $allowed);

        if ($prefix === null) {
            $findings[] = new Finding(
                'title-kind-mismatch',
                sprintf(
                    'Title "%s" does not open with an allowed prefix for %s — expected one of: %s.',
                    $title, $kind, implode(', ', $allowed),
                ),
                $severities['title-kind-mismatch'] ?? 'error',
            );
        }
    }

    /** @return list<string> */
    private static function allowedPrefixes(string $kind): array
    {
        return match ($kind) {
            'kind:feature' => ['feat'],
            'kind:bugfix' => ['fix'],
            'kind:release' => ['release'],
            'kind:chore' => ['chore', 'refactor', 'test', 'docs', 'build', 'ci', 'perf', 'style'],
        };
    }

    /**
     * Parses the conventional prefix of a title as `^(prefix)(\(|!?:)`.
     *
     * @param  list<string>  $allowed
     * @return string|null The matched prefix, or null when the title opens
     *                     with nothing allowed for the kind.
     */
    private static function parsePrefix(string $title, array $allowed): ?string
    {
        foreach ($allowed as $prefix) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\(|!?:)/', $title) === 1) {
                return $prefix;
            }
        }

        return null;
    }

    /** A package-epic reports no work; it indexes it, so it carries no kind. */
    private function kindForbidden(?string $level): bool
    {
        return $level !== null && $this->schema->levelKindForbidden($level);
    }

    private function sectionsExempt(?string $level): bool
    {
        return $level !== null && $this->schema->levelSectionsExempt($level);
    }

    /**
     * The kind and level this input resolves to, without validating.
     *
     * The CI gate reads this to apply the `level:*` label an issue declares
     * only in its body (a web-filed issue carries no such label), reusing the
     * exact resolution the validator uses — no second parser (spec D12).
     *
     * @param  array{body?: string, labels?: list<string>}  $input
     * @return array{kind: string|null, level: string|null}
     */
    public function classify(array $input): array
    {
        $labels = self::strings($input['labels'] ?? []);
        $parsed = SectionParser::parse($input['body'] ?? '');
        $ignored = [];
        $level = $this->resolveLevel($labels, $parsed, $ignored);

        return [
            'kind' => $this->resolveKind($labels, $ignored, !$this->kindForbidden($level)),
            'level' => $level,
        ];
    }

    /**
     * The issue's kind is carried only by its labels (spec D8); exactly one
     * of the schema's kinds must be present.
     *
     * @param  list<string>  $labels
     * @param  list<Finding>  $findings
     */
    private function resolveKind(array $labels, array &$findings, bool $required = true): ?string
    {
        $kinds = array_values(array_intersect($labels, $this->schema->kinds()));

        if ($kinds === []) {
            if ($required) {
                $findings[] = new Finding('missing-kind-label', sprintf(
                    'Add exactly one kind label: %s.', implode(', ', $this->schema->kinds()),
                ));
            }

            return null;
        }

        if (count($kinds) > 1) {
            $findings[] = new Finding('multiple-kind-labels', sprintf(
                'Issue carries multiple kind labels (%s); keep exactly one.', implode(', ', $kinds),
            ));

            return null;
        }

        return $kinds[0];
    }

    /**
     * The level comes from a `level:*` label when present, else from the
     * form's Level dropdown (spec ruling 7) so a human-filed issue is not
     * false-flagged.
     *
     * @param  list<string>  $labels
     * @param  array<string, list<string>>  $parsed
     * @param  list<Finding>  $findings
     */
    private function resolveLevel(array $labels, array $parsed, array &$findings): ?string
    {
        $levels = array_keys($this->schema->levels());
        $declared = array_values(array_intersect($labels, $levels));

        if (count($declared) > 1) {
            $findings[] = new Finding('multiple-level-labels', sprintf(
                'Issue carries multiple level labels (%s); keep exactly one.', implode(', ', $declared),
            ));

            return null;
        }

        if ($declared !== []) {
            return $declared[0];
        }

        foreach ($parsed[SectionParser::normalise('Level')] ?? [] as $block) {
            $candidate = trim($block);

            if (in_array($candidate, $levels, true)) {
                return $candidate;
            }
        }

        $findings[] = new Finding('missing-level-label', sprintf(
            'No level declared: add a level:* label or fill the Level field (%s).', implode(', ', $levels),
        ));

        return null;
    }

    /**
     * @param  array<string, list<string>>  $parsed
     * @param  list<Finding>  $findings
     */
    private function checkSections(string $kind, array $parsed, array &$findings): void
    {
        foreach ($this->schema->requiredIdsFor($kind) as $id) {
            $section = $this->schema->section($id);
            $key = SectionParser::normalise($section['heading']);

            if (!array_key_exists($key, $parsed)) {
                $findings[] = new Finding('missing-section', sprintf(
                    'Missing required section "%s".', $section['heading'],
                ), 'error', $id);

                continue;
            }

            if (!$this->hasContent($parsed[$key], $section['placeholder'])) {
                $findings[] = new Finding('empty-section', sprintf(
                    'Section "%s" is empty — replace the placeholder with real content.', $section['heading'],
                ), 'error', $id);
            }
        }
    }

    /**
     * A section is satisfied if any occurrence holds real content. A block is
     * empty when it is whitespace, an HTML comment, the schema placeholder, or
     * nothing but bare unchecked checkboxes.
     *
     * @param  list<string>  $blocks
     */
    private function hasContent(array $blocks, string $placeholder): bool
    {
        $expected = SectionParser::normalise($placeholder);

        foreach ($blocks as $block) {
            $text = trim(preg_replace('/<!--.*?-->/s', '', $block) ?? '');

            if ($text === '' || $text === self::NO_RESPONSE || SectionParser::normalise($text) === $expected) {
                continue;
            }

            foreach (preg_split('/\R/', $text) ?: [] as $line) {
                $line = trim($line);

                // A written criterion counts; a bare unchecked box does not.
                if ($line !== '' && preg_match('/^[-*+]\s*\[\s*\]\s*$/u', $line) !== 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<Finding> $findings */
    private function checkLevel(string $level, ?int $parent, ?string $parentLevel, array &$findings): void
    {
        $parentRule = $this->schema->levels()[$level]['parent'] ?? null;
        $allowed = is_array($parentRule) ? self::strings($parentRule['allowed_parent_levels'] ?? []) : [];
        $required = is_array($parentRule) && ($parentRule['required'] ?? false);

        if ($parent === null) {
            if ($required) {
                $findings[] = new Finding('parent-missing', sprintf(
                    'A %s issue must sit under a parent (%s). Link one, or file the parent first.',
                    $level, implode(', ', $allowed),
                ));
            }

            return;
        }

        if ($parentRule === 'forbidden') {
            $findings[] = new Finding('parent-forbidden', sprintf(
                'A %s issue must not have a parent; remove parent #%d.', $level, $parent,
            ));

            return;
        }

        if ($parentLevel === null) {
            $findings[] = new Finding('parent-missing', sprintf(
                'parent #%d has no level:* label — add one or pick a new-style parent.', $parent,
            ));

            return;
        }

        $parentDefinition = $this->schema->levels()[$parentLevel] ?? null;

        if (is_array($parentDefinition) && ($parentDefinition['children'] ?? null) === 'forbidden') {
            $findings[] = new Finding('children-forbidden', sprintf(
                'parent #%d is a %s and cannot have children.', $parent, $parentLevel,
            ));

            return;
        }

        if (!in_array($parentLevel, $allowed, true)) {
            $findings[] = new Finding('parent-level-invalid', sprintf(
                'A %s issue must sit under %s; parent #%d is %s.',
                $level, implode(', ', $allowed), $parent, $parentLevel,
            ));
        }
    }

    /**
     * A package-epic names one primary package — the package it is *mostly*
     * about (spec D14). This is the one invariant the level adds beyond the
     * parent chain.
     *
     * @param  list<string>  $labels
     * @param  list<Finding>  $findings
     */
    private function checkPackageLabel(string $level, array $labels, array &$findings): void
    {
        if (!$this->schema->levelRequiresPackageLabel($level)) {
            return;
        }

        $packages = array_values(array_filter(
            $labels,
            static fn (string $label): bool => str_starts_with($label, 'package:'),
        ));

        if ($packages === []) {
            $findings[] = new Finding('missing-package-label', sprintf(
                'A %s issue must name exactly one primary package (add a package:* label).', $level,
            ));

            return;
        }

        if (count($packages) > 1) {
            $findings[] = new Finding('multiple-package-labels', sprintf(
                '%s carries several package labels (%s); keep the package it is mostly about.',
                $level, implode(', ', $packages),
            ));
        }
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
