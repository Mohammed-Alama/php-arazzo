<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * Renders and splices the generated index inside a package-epic issue body.
 *
 * The index is wrapped in `<!-- package-index: generated -->` … `<!-- /package-index -->`
 * markers so it can be regenerated without clobbering human-written text outside
 * those markers (spec §A7).
 */
final class PackageIndex
{
    public const START = '<!-- package-index: generated -->';

    public const END = '<!-- /package-index -->';

    /**
     * Render the index for a package's epics.
     *
     * @param  string  $package  The package name (e.g. 'runner').
     * @param  list<array{number: int, title: string, state: string, stories: int}>  $epics
     * @return string The index block, wrapped in markers, with a trailing newline.
     */
    public function render(string $package, array $epics): string
    {
        $openCount = 0;
        foreach ($epics as $epic) {
            if (($epic['state'] ?? '') === 'OPEN') {
                $openCount++;
            }
        }

        $lines = [];
        $lines[] = self::START;
        $lines[] = "**Every epic under `{$package}`.** {$openCount} open.";
        $lines[] = '';

        if ($epics === []) {
            $lines[] = 'No epics yet. File one with `--level=level:epic --parent=<this issue>`.';
        } else {
            // Sort by number ascending
            usort($epics, static fn (array $a, array $b): int => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));

            $lines[] = '| Epic | State | Stories |';
            $lines[] = '| --- | --- | --- |';

            foreach ($epics as $epic) {
                $number = $epic['number'] ?? 0;
                $title = $epic['title'] ?? '';
                $state = strtolower($epic['state'] ?? 'OPEN');
                $stories = $epic['stories'] ?? 0;

                $lines[] = "| [#{$number}]({$number}) {$title} | {$state} | {$stories} |";
            }
        }

        $lines[] = self::END;
        $lines[] = ''; // trailing newline

        return implode("\n", $lines);
    }

    /**
     * Replace the generated index region in a body with a new block.
     *
     * If both markers exist and END follows START, the span from START through
     * END inclusive is replaced. Otherwise the new block is appended to the
     * current body, separated by a blank line (or with no prefix when the body
     * is blank).
     *
     * @param  string  $currentBody  The existing issue body.
     * @param  string  $newBlock  The new index block (output of render()).
     * @return string The body with the index replaced/appended.
     */
    public function splice(string $currentBody, string $newBlock): string
    {
        $startPos = strpos($currentBody, self::START);

        if ($startPos === false) {
            if ($currentBody === '' || trim($currentBody) === '') {
                return $newBlock;
            }

            return rtrim($currentBody)."\n\n".$newBlock;
        }

        $endPos = strpos($currentBody, self::END, $startPos);

        if ($endPos === false) {
            if ($currentBody === '' || trim($currentBody) === '') {
                return $newBlock;
            }

            return rtrim($currentBody)."\n\n".$newBlock;
        }

        $endPos += strlen(self::END);

        // Include a single trailing newline after END in the replaced span if present
        if (isset($currentBody[$endPos]) && $currentBody[$endPos] === "\n") {
            $endPos++;
        }

        return substr_replace($currentBody, $newBlock, $startPos, $endPos - $startPos);
    }

    /**
     * Return the empty index for a new package-epic.
     *
     * @param  string  $package  The package name.
     * @return string The empty index block (markers + preamble + no epics message).
     */
    public function stub(string $package): string
    {
        return $this->render($package, []);
    }
}
